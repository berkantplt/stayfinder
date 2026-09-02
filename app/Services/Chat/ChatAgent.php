<?php

namespace App\Services\Chat;

use App\Services\Chat\Tools\ChatTool;
use App\Services\Chat\Tools\EnvanterOzeti;
use App\Services\Chat\Tools\SehirBilgisi;
use App\Services\Chat\Tools\TurAra;
use App\Services\Chat\Tools\TurDetay;
use App\Support\OpenAiChatParams;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Chatbot v2 çekirdeği: model → araç → model döngüsü.
 *
 * Model düşünür ve yazar; katalog gerçeklerini araçlardan alır. v1'in elle
 * yazılmış 10 fazlı regex zinciri yok — hangi aracın çağrılacağına model karar
 * verir, her araç ayrı ayrı test edilebilir.
 *
 * Döngü SENKRON koşar, yalnız nihai cevap stream edilir: openai-php'de
 * StreamResponse::fake() olmadığı için streaming araç çağrısı test edilemiyor;
 * senkron döngü mevcut hermetik test düzenini bozmadan çalışır.
 */
class ChatAgent
{
    /** @var array<string, ChatTool> */
    private array $tools;

    private const GECERLI_ROLLER = ['user', 'assistant'];

    public function __construct(
        TurAra $turAra,
        TurDetay $turDetay,
        SehirBilgisi $sehirBilgisi,
        EnvanterOzeti $envanterOzeti,
        private readonly ResponseValidator $validator,
        private readonly ReferenceDestinationDetector $referansTespiti,
    ) {
        $this->tools = [
            TurAra::name() => $turAra,
            TurDetay::name() => $turDetay,
            SehirBilgisi::name() => $sehirBilgisi,
            EnvanterOzeti::name() => $envanterOzeti,
        ];
    }

    /** Araç adı → kullanıcıya gösterilecek faz metni (SSE "faz" olayı). */
    private const FAZ_METINLERI = [
        'tur_ara' => 'Turları tarıyorum…',
        'tur_detay' => 'Tur detayına bakıyorum…',
        'sehir_bilgisi' => 'Destinasyonu inceliyorum…',
        'envanter_ozeti' => 'Katalogda ne var bakıyorum…',
    ];

    /**
     * @param  array<int, array{role: string, content: string}>  $gecmis
     * @return array{metin: string, turlar: array, durum: ConversationState, arac_turlari: int, dusurulen: string[], hata: bool, iz: array}
     */
    public function handle(string $mesaj, array $gecmis = [], ?ConversationState $durum = null, ?\Closure $emit = null, ?\Closure $faz = null): array
    {
        $durum ??= new ConversationState();
        $model = config('ai.chat_agent_model', 'gpt-5.4');
        $maxTur = max(1, (int) config('ai.chat_max_tool_rounds', 3));

        $messages = $this->mesajListesi($mesaj, $gecmis, $durum);
        $transkript = $this->kullaniciTranskripti($gecmis, $mesaj);
        $semalar = array_map(fn ($t) => $t::schema(), [TurAra::class, TurDetay::class, SehirBilgisi::class, EnvanterOzeti::class]);

        $aracSonuclari = [];
        $turlar = [];
        $akitilan = '';   // kullanıcıya gerçekten gösterilen metin (geçmişe bu kaydedilir)
        $aracTuru = 0;
        $iz = [];         // araç çağrı dizisi — eval assert'leri metinde değil BURADA yapılır

        for ($tur = 0; $tur <= $maxTur; $tur++) {
            $sonTur = $tur === $maxTur; // son turda araçlar kapanır: model cevabı YAZMAK zorunda

            try {
                $response = OpenAI::chat()->create(OpenAiChatParams::tools(
                    $model, $messages, $sonTur ? [] : $semalar, 1200,
                ));
            } catch (\Throwable $e) {
                Log::warning('[ChatAgent] LLM çağrısı başarısız', ['error' => $e->getMessage(), 'tur' => $tur]);

                return $this->finalize('', $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan, true, $iz);
            }

            $message = $response->choices[0]->message ?? null;
            $toolCalls = $message->toolCalls ?? [];

            if ($toolCalls === []) {
                return $this->finalize((string) ($message->content ?? ''), $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan, false, $iz);
            }

            $akitilan .= $this->yansitmayiAkit((string) ($message->content ?? ''), $aracSonuclari, $durum, $emit);

            $messages[] = [
                'role' => 'assistant',
                'content' => $message->content,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => ['name' => $tc->function->name, 'arguments' => $tc->function->arguments],
                ], $toolCalls),
            ];

            $this->araclariCalistir($toolCalls, $transkript, $durum, $faz, $messages, $aracSonuclari, $turlar, $iz);

            $aracTuru++;
        }

        return $this->finalize('', $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan, false, $iz);
    }

    /**
     * LLM'e gidecek mesaj listesi: sistem promptu + durum özeti + geçmiş + yeni mesaj.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function mesajListesi(string $mesaj, array $gecmis, ConversationState $durum): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt()]];
        if ($ozet = $durum->promptOzeti()) {
            $messages[] = ['role' => 'system', 'content' => $ozet];
        }
        // Geçmişte yalnız user/assistant kabul edilir: 'system' rolü sızarsa
        // konuşma geçmişi üzerinden prompt enjeksiyonu mümkün olurdu
        foreach ($gecmis as $m) {
            $rol = $m['role'] ?? null;
            $icerik = $m['content'] ?? null;
            if (in_array($rol, self::GECERLI_ROLLER, true) && is_string($icerik) && $icerik !== '') {
                $messages[] = ['role' => $rol, 'content' => $icerik];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $mesaj];

        return $messages;
    }

    /**
     * Model araç çağırırken yanına cümle de yazdıysa (yansıtma) anında akıt —
     * araçlar koşarken kullanıcı sessizlik görmesin. Bu metin de DOĞRULAMADAN
     * geçer: aksi halde uydurma sayı bu kanaldan denetimsiz sızardı.
     *
     * @return string akıtılan parça ("\n\n" son ekiyle) — akıtılmadıysa boş
     */
    private function yansitmayiAkit(string $yansitma, array $aracSonuclari, ConversationState $durum, ?\Closure $emit): string
    {
        $yansitma = trim($yansitma);
        if ($yansitma === '') {
            return '';
        }

        $temiz = $this->validator->temizle($yansitma, $aracSonuclari, $durum->bilinenSayilar());
        if ($temiz['metin'] === '' || ! $emit) {
            return '';
        }

        $emit($temiz['metin']."\n\n");

        return $temiz['metin']."\n\n";
    }

    /**
     * Bir turdaki araç çağrılarını sırayla koşar; mesaj listesi, sonuç birikimi,
     * kart şeridi ve izi yerinde (by-ref) günceller.
     */
    private function araclariCalistir(
        iterable $toolCalls,
        string $transkript,
        ConversationState $durum,
        ?\Closure $faz,
        array &$messages,
        array &$aracSonuclari,
        array &$turlar,
        array &$iz,
    ): void {
        foreach ($toolCalls as $tc) {
            $ad = $tc->function->name;
            $args = json_decode($tc->function->arguments ?: '{}', true);
            if (! is_array($args)) {
                Log::warning('[ChatAgent] Araç argümanı ayrıştırılamadı', ['arac' => $ad]);
                $args = [];
            }

            // Faz bildirimi: model yansıtma cümlesi yazmadığında kullanıcı
            // araçlar koşarken tamamen sessizlik görüyordu. Aynı zamanda
            // istemci bekçisini (90 sn) diri tutar.
            if ($faz && isset(self::FAZ_METINLERI[$ad])) {
                $faz(self::FAZ_METINLERI[$ad]);
            }

            // Filtre hazırlığı runTool'dan ÖNCE: absorb da aynı düzeltilmiş
            // filtreyi görsün. Aksi halde modelin ham filtresi hafızaya yazılır,
            // düzeltilen kısıt bir sonraki turda geri gelirdi.
            if ($ad === TurAra::name()) {
                $args = $this->turAraArgumanlari($args, $transkript, $durum);
            }

            $sonuc = $this->runTool($ad, $args, $transkript, $durum);
            $durum->absorb($ad, $args, $sonuc);
            $aracSonuclari[] = $sonuc;
            $iz[] = $this->izKaydi($ad, $args, $sonuc);

            // Kartlar SONUÇ ÜRETEN her aramayla tazelenir. Hatalı çağrı (boyut
            // doldurulamadı) iyi kartları silmez.
            //
            // Boş sonuç şeridi SİLMEZ — önceki kural buydu ("daraltılmış ikinci
            // arama boş dönerse eski kartlar kalmasın") ama canlıda ters tepti:
            // model bir turda iki kez arıyor, ikincisi boş dönüyor ve kullanıcı
            // turların adını metinde okuyup altında hiç kart göremiyordu. Bu
            // kesin bir hata; eskisi ise ihtimal ("bulamadım" derken eski kartlar
            // duruyor olabilir) — üstelik model o metni yazarken ilk aramanın
            // sonucu da elinde, genelde onlardan söz ediyor.
            if ($ad === TurAra::name() && ! isset($sonuc['hata'])) {
                // Yakın turlar şeride EKLENİR ama 'yakin' bayrağı taşırlar;
                // arayüz onları ayrı çerçevede gösterir, model de ayrı anlatır.
                $yeniKartlar = array_merge($sonuc['turlar'] ?? [], $sonuc['yakin_turlar'] ?? []);
                if ($yeniKartlar !== []) {
                    $turlar = $yeniKartlar;
                }
            }

            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $tc->id,
                'content' => json_encode($sonuc, JSON_UNESCAPED_UNICODE),
            ];
        }
    }

    /**
     * tur_ara argümanlarını arama ÖNCESİ düzeltir.
     *
     * Üç iş: (1) oturumdaki kısıtları taşı — model tekrar geçirmeyi unutursa
     * arama sert filtresiz koşmasın, (2) kullanıcının vazgeçtiği kısıtları
     * düşür, (3) kıyas için anılan yeri destinasyon'dan referans_yer'e taşı.
     *
     * Kıyas taşıması "kaldirilan_kisitlar"a da yazılır: absorb() kısıtları
     * BİRLEŞTİRDİĞİ için, yalnız filtreden silmek yetmez — hafızadaki eski
     * destinasyon olduğu yerde kalır ve bir sonraki turda geri gelirdi.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function turAraArgumanlari(array $args, string $transkript, ConversationState $durum): array
    {
        $filtre = array_merge($durum->varsayilanFiltre(), (array) ($args['filtre'] ?? []));

        $kaldirilan = array_values(array_filter(
            (array) ($args['kaldirilan_kisitlar'] ?? []),
            fn ($a) => is_string($a),
        ));
        foreach ($kaldirilan as $anahtar) {
            unset($filtre[$anahtar]);
        }

        $duzeltilmis = $this->referansTespiti->apply($filtre, $transkript);

        foreach (array_keys($filtre) as $anahtar) {
            if (! array_key_exists($anahtar, $duzeltilmis) && ! in_array($anahtar, $kaldirilan, true)) {
                $kaldirilan[] = $anahtar;
            }
        }

        $args['filtre'] = $duzeltilmis;
        $args['kaldirilan_kisitlar'] = $kaldirilan;

        return $args;
    }

    /**
     * İz kaydı tek girdisi — eval assert'leri metinde değil BURADA yapılır,
     * alan adları sözleşmedir (arac, args, tur_sayisi, toplam_eslesme, hata,
     * olculemeyen_boyutlar).
     */
    private function izKaydi(string $ad, array $args, array $sonuc): array
    {
        return [
            'arac' => $ad,
            'args' => $args,
            'tur_sayisi' => count($sonuc['turlar'] ?? []),
            'toplam_eslesme' => $sonuc['toplam_eslesme'] ?? null,
            'hata' => $sonuc['hata'] ?? null,
            'olculemeyen_boyutlar' => $sonuc['olculemeyen_boyutlar'] ?? [],
        ];
    }

    private function runTool(string $ad, array $args, string $transkript, ConversationState $durum): array
    {
        $tool = $this->tools[$ad] ?? null;
        if (! $tool) {
            return ['hata' => 'Bilinmeyen araç: '.$ad];
        }

        if ($ad === TurAra::name()) {
            // Kanıt doğrulaması sunucu tarafında: transkripti MODEL vermez, biz ekleriz
            $args['transkript'] = $transkript;
            // Filtre araclariCalistir'da hazırlandı (birleştirme + vazgeçilenler
            // + kıyas ayrımı); burada yeniden hazırlanmaz — oturum kısıtları
            // ikinci kez birleşse silinen destinasyon geri gelirdi.
            $args['filtre'] = is_array($args['filtre'] ?? null) ? $args['filtre'] : [];
        }

        try {
            return $tool->run($args);
        } catch (\Throwable $e) {
            Log::warning('[ChatAgent] Araç hatası', ['arac' => $ad, 'error' => $e->getMessage()]);

            return ['hata' => 'Araç şu an çalışmadı; bu bilgiyi kullanıcıya verme.'];
        }
    }

    /** @return array{metin: string, turlar: array, durum: ConversationState, arac_turlari: int, dusurulen: string[], hata: bool, iz: array} */
    private function finalize(
        string $ham,
        array $aracSonuclari,
        array $turlar,
        ConversationState $durum,
        int $aracTuru,
        ?\Closure $emit,
        string $akitilan = '',
        bool $hata = false,
        array $iz = [],
    ): array {
        $temiz = $this->validator->temizle($ham, $aracSonuclari, $durum->bilinenSayilar());
        $metin = $temiz['metin'];

        if ($temiz['dusurulen'] !== []) {
            Log::info('[ChatAgent] Doğrulama cümle düşürdü', [
                'adet' => count($temiz['dusurulen']),
                'ornek' => mb_substr($temiz['dusurulen'][0], 0, 120, 'UTF-8'),
            ]);
        }

        if (trim($metin) === '' && trim($akitilan) === '') {
            $metin = match (true) {
                $hata => 'Şu an bir aksilik oldu, tekrar dener misin?',
                $turlar !== [] => 'Sana uygun olabilecek turları aşağıya bıraktım — hangisini merak ettin?',
                default => 'Bunu tam yakalayamadım, biraz daha anlatır mısın?',
            };
        }

        if ($metin !== '' && $emit) {
            $emit($metin);
        }

        return [
            // Kullanıcının GÖRDÜĞÜ metnin tamamı: yansıtma + nihai cevap. Geçmişe
            // bu kaydedilir, yoksa model kendi söylediğini bir sonraki turda bilmez.
            'metin' => trim($akitilan.$metin),
            'turlar' => $turlar,
            'durum' => $durum,
            'arac_turlari' => $aracTuru,
            'dusurulen' => $temiz['dusurulen'],
            'hata' => $hata,
            'iz' => $iz,
        ];
    }

    private function kullaniciTranskripti(array $gecmis, string $mesaj): string
    {
        $satirlar = [];
        foreach ($gecmis as $m) {
            if (($m['role'] ?? null) === 'user' && is_string($m['content'] ?? null)) {
                $satirlar[] = $m['content'];
            }
        }
        $satirlar[] = $mesaj;

        return implode("\n", $satirlar);
    }

    /**
     * Sezon + özel dönem HESABI burada, metin şablonu ChatPrompts'ta.
     * Üretilen prompt taşınma öncesiyle byte-aynı.
     */
    public function systemPrompt(): string
    {
        $bugun = now();
        $sezon = match ((int) $bugun->format('n')) {
            12, 1, 2 => 'kış',
            3, 4, 5 => 'ilkbahar',
            6, 7, 8 => 'yaz',
            default => 'sonbahar',
        };

        // Süren VE yaklaşan dönemler, tarihe göre sıralı (yalnız başlangıca
        // bakmak, bayramın tam ortasında dönemi listeden düşürüyordu)
        $donemler = [];
        foreach (config('special_periods', []) as $p) {
            foreach ($p['ranges'] ?? [] as [$bas, $bit]) {
                if ($bugun->gt($bit)) {
                    continue;
                }
                if ($bugun->between($bas, $bit)) {
                    $donemler[$bas] = $p['label'].' (şu an sürüyor)';
                } elseif ($bugun->diffInDays($bas) <= 240) {
                    $donemler[$bas] = $p['label'].' ('.$bas.')';
                }
            }
        }
        ksort($donemler);
        $ozelGunler = implode(', ', array_slice(array_values($donemler), 0, 4));
        $ozelGunSatiri = $ozelGunler !== '' ? "YAKLAŞAN ÖZEL DÖNEMLER: {$ozelGunler}" : '';

        return ChatPrompts::system($bugun->format('d.m.Y'), $sezon, $ozelGunSatiri);
    }
}
