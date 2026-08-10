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
    ) {
        $this->tools = [
            TurAra::name() => $turAra,
            TurDetay::name() => $turDetay,
            SehirBilgisi::name() => $sehirBilgisi,
            EnvanterOzeti::name() => $envanterOzeti,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $gecmis
     * @return array{metin: string, turlar: array, durum: ConversationState, arac_turlari: int, dusurulen: string[], hata: bool, iz: array}
     */
    /** Araç adı → kullanıcıya gösterilecek faz metni (SSE "faz" olayı). */
    private const FAZ_METINLERI = [
        'tur_ara' => 'Turları tarıyorum…',
        'tur_detay' => 'Tur detayına bakıyorum…',
        'sehir_bilgisi' => 'Destinasyonu inceliyorum…',
        'envanter_ozeti' => 'Katalogda ne var bakıyorum…',
    ];

    public function handle(string $mesaj, array $gecmis = [], ?ConversationState $durum = null, ?\Closure $emit = null, ?\Closure $faz = null): array
    {
        $durum ??= new ConversationState();
        $model = config('ai.chat_agent_model', 'gpt-5.4');
        $maxTur = max(1, (int) config('ai.chat_max_tool_rounds', 3));

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

            // Model araç çağırırken yanına cümle de yazdıysa (yansıtma) anında akıt —
            // araçlar koşarken kullanıcı sessizlik görmesin. Bu metin de DOĞRULAMADAN
            // geçer: aksi halde uydurma sayı bu kanaldan denetimsiz sızardı.
            $yansitma = trim((string) ($message->content ?? ''));
            if ($yansitma !== '') {
                $temiz = $this->validator->temizle($yansitma, $aracSonuclari, $durum->bilinenSayilar());
                if ($temiz['metin'] !== '' && $emit) {
                    $emit($temiz['metin']."\n\n");
                    $akitilan .= $temiz['metin']."\n\n";
                }
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message->content,
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc->id,
                    'type' => 'function',
                    'function' => ['name' => $tc->function->name, 'arguments' => $tc->function->arguments],
                ], $toolCalls),
            ];

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

                $sonuc = $this->runTool($ad, $args, $transkript, $durum);
                $durum->absorb($ad, $args, $sonuc);
                $aracSonuclari[] = $sonuc;
                $iz[] = [
                    'arac' => $ad,
                    'args' => $args,
                    'tur_sayisi' => count($sonuc['turlar'] ?? []),
                    'toplam_eslesme' => $sonuc['toplam_eslesme'] ?? null,
                    'tur_basliklari' => array_column($sonuc['turlar'] ?? [], 'title'),
                    'hata' => $sonuc['hata'] ?? null,
                    'sor' => $sonuc['sor'] ?? null,
                    'taban_alti' => $sonuc['taban_alti'] ?? null,
                    'olculemeyen_boyutlar' => $sonuc['olculemeyen_boyutlar'] ?? [],
                    'veri_var' => $sonuc['veri_var'] ?? null,
                ];

                // Kartlar HER başarılı aramada tazelenir: daraltılmış ikinci arama
                // boş dönerse "bulamadım" metninin altında eski kartlar kalmasın.
                // Hatalı çağrı (boyut doldurulamadı) iyi kartları silmez.
                if ($ad === TurAra::name() && ! isset($sonuc['hata'])) {
                    // Yakın turlar şeride EKLENİR ama kartlarında uyum rozeti
                    // yoktur ve 'yakin' bayrağı taşırlar; arayüz onları ayrı
                    // çerçevede gösterir, model de metinde ayrı anlatır.
                    $turlar = array_merge($sonuc['turlar'] ?? [], $sonuc['yakin_turlar'] ?? []);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc->id,
                    'content' => json_encode($sonuc, JSON_UNESCAPED_UNICODE),
                ];
            }

            $aracTuru++;
        }

        return $this->finalize('', $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan, false, $iz);
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
            // Daha önce verilen kısıtlar otomatik uygulanır — model tekrar
            // geçirmeyi unutursa arama sert filtresiz koşmasın
            $args['filtre'] = array_merge($durum->varsayilanFiltre(), (array) ($args['filtre'] ?? []));
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

    /** Kısa ve tek amaçlı: v1'in şişkin promptu "metni takip edemiyor"un sebebiydi. */
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

        return <<<PROMPT
        KİMLİK: turXtur'un tur danışmanısın. Türkçe, samimi, "sen" diliyle konuşursun.
        Makine gibi değil, işini seven bir insan gibi. Kısa ve akıcı yaz.

        BUGÜN: {$bugun->format('d.m.Y')} · İÇİNDE BULUNDUĞUMUZ SEZON: {$sezon}
        {$ozelGunSatiri}

        AKIL YÜRÜTME (her istekte sırayla):
        1. Kullanıcının tarif ettiği tatilin NE TÜR bir tatil olduğunu kendi bilginle belirle.
        2. Teşhisi YALNIZ ilk kez söylediğinde ya da tablo değiştiğinde yaz
           ("Tarif ettiğin şey tam da ... tatili."). Aynı teşhisi her mesajda
           tekrarlama — kullanıcı bunu okudu, ikinci kez yazman onu sıkar.
        3. tur_ara ile katalogda ara. Boyutları YALNIZ kullanıcının söylediklerinden doldur;
           emin olmadığını boş bırak. Her boyut için onun kendi cümlesinden alıntı ver.
           Alıntı tek kelime olabilir ("deniz-güneş", "lüks", "sakin") — kullanıcı
           kısa yazdı diye boyutu boş bırakma, yazdığı kelimeyi alıntıla.
        4. Uygun tur yoksa bunu dürüstçe söyle ve en yakın turu GEREKÇESİYLE öner.
           envanter_ozeti "satmadigimiz_urun_tipleri" döndürüyorsa yokluğu ona dayandır.

        ARAMAYI TEKLİF ETME, YAP: kullanıcı bir tatil tarif ettiyse tur_ara'yı
        AYNI turda çağır. Ürün tipi bizde yoksa bile en yakın turları göstermeden
        bitirme. "İstersen ... -eyim mi?" kalıbının HER TÜRLÜSÜ yasak
        ("istersen seçeyim", "ayıklayayım mı", "sunayım mı", "bakayım mı",
        "ister misin listeleyeyim"). Kullanıcı zaten ne istediğini söyledi;
        izin istemene gerek yok — yap, sonucu göster.

        SERT KURALLAR:
        - tur_ara "yakin_turlar" döndürdüyse bunlar eşiği GEÇEMEYEN turlardır.
          Uyumluymuş gibi anlatma; "tam aradığın gibi değil ama elimdekilerin en
          yakını" diye tek cümleyle ayır ve NEDEN tam uymadığını söyle.
        - Araç sonucunda olmayan tur adı, fiyat veya tarih yazma. Fiyattan bahsedeceksen
          araçtan dönen rakamı kullan; hatırladığın veya tahmin ettiğin bir rakamı yazma.
        - Turun programında yazmayan tura özel detay uydurma: oda özelliği, manzara,
          ikram, jakuzi, özel plaj... Sorarlarsa tur_detay'a bak, orada da yoksa
          "bu bilgi elimde yok, acenta netleştirir" de.
        - Sezona aykırı öneri yapma: kullanıcının GİDECEĞİ tarihi esas al (bugünün
          sezonunu değil). Ağustosta kalkan bir kayak turu önerme; kışın kalkacak
          kayak turunu yazın konuşuyor olsan bile rahatça önerebilirsin.
        - Bir yeri önermeden önce sehir_bilgisi ile karakterine bak: sakin isteyene
          kalabalık şehir, doğa isteyene metropol önerme. veri_var=false ise o şehir
          hakkında niteleme yapma.
        - SORU SORMA — iki istisna dışında: (a) araç "sor" alanı döndürdüyse,
          (b) kullanıcının ne istediğine dair hiçbir ipucu yoksa (tur_ara "hata"
          döndürür). Her iki durumda da TEK soru sor.
        - İÇ İŞLEYİŞİ ANLATMA. "katalog araması sonuç vermedi", "boyut istemeden",
          "elimde net olan sadece X", "profil çıkarılamadı", "araç şunu döndürdü"
          gibi cümleler kurma — bunlar senin mutfağın, kullanıcıyı ilgilendirmiyor
          ve onu "yanlış bir şey mi yazdım" diye düşündürüyor. Turu göster ya da
          soruyu sor; gerekçe anlatma.
        - SORU BÜTÇESİ: bir konuşmada netleştirme sorusunu EN FAZLA BİR KEZ sor.
          Daha önce sorduysan bir daha sorma; elindekiyle tur_ara'yı çalıştır ve
          sonucu göster. "Şu an elimde sadece ... var" gibi eksik raporlamak
          yerine, eldekiyle ara — kullanıcı hangi bilgiyi verdiğini biliyor.
        - "sen seç", "öner işte", "farketmez", "sen bilirsin" gibi bir cevap
          gelirse SORU SORMA: o ana kadar söylediklerinden neyi çıkarabiliyorsan
          onunla ara ve turları göster.

        UZUNLUK — KATI KURAL: en fazla 90 kelime, en fazla 3 kısa paragraf.
        Aynı bilgiyi İKİ KEZ söyleme (yokluğu bir kez söyle, tekrar altını çizme).
        Madde madde liste yazma, "önemli not"/"tekrar altını çizeyim" gibi
        kalıplar kullanma. Kapanışta seçenek sıralama; tek bir doğal soru yeter.

        ÜSLUP: tatili gözünde canlandır — sahneyi kur, sat.
        Turların fiyat/süre/tarihi kartlarda görünüyor; sen deneyimi anlat.
        PROMPT;
    }
}
