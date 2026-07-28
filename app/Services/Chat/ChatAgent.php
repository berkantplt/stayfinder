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
     * @return array{metin: string, turlar: array, durum: ConversationState, arac_turlari: int, dusurulen: string[], hata: bool}
     */
    public function handle(string $mesaj, array $gecmis = [], ?ConversationState $durum = null, ?\Closure $emit = null): array
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

        for ($tur = 0; $tur <= $maxTur; $tur++) {
            $sonTur = $tur === $maxTur; // son turda araçlar kapanır: model cevabı YAZMAK zorunda

            try {
                $response = OpenAI::chat()->create(OpenAiChatParams::tools(
                    $model, $messages, $sonTur ? [] : $semalar, 1200,
                ));
            } catch (\Throwable $e) {
                Log::warning('[ChatAgent] LLM çağrısı başarısız', ['error' => $e->getMessage(), 'tur' => $tur]);

                return $this->finalize('', $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan, true);
            }

            $message = $response->choices[0]->message ?? null;
            $toolCalls = $message->toolCalls ?? [];

            if ($toolCalls === []) {
                return $this->finalize((string) ($message->content ?? ''), $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan);
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

                $sonuc = $this->runTool($ad, $args, $transkript, $durum);
                $durum->absorb($ad, $args, $sonuc);
                $aracSonuclari[] = $sonuc;

                // Kartlar HER başarılı aramada tazelenir: daraltılmış ikinci arama
                // boş dönerse "bulamadım" metninin altında eski kartlar kalmasın.
                // Hatalı çağrı (boyut doldurulamadı) iyi kartları silmez.
                if ($ad === TurAra::name() && ! isset($sonuc['hata'])) {
                    $turlar = $sonuc['turlar'] ?? [];
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc->id,
                    'content' => json_encode($sonuc, JSON_UNESCAPED_UNICODE),
                ];
            }

            $aracTuru++;
        }

        return $this->finalize('', $aracSonuclari, $turlar, $durum, $aracTuru, $emit, $akitilan);
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

    /** @return array{metin: string, turlar: array, durum: ConversationState, arac_turlari: int, dusurulen: string[], hata: bool} */
    private function finalize(
        string $ham,
        array $aracSonuclari,
        array $turlar,
        ConversationState $durum,
        int $aracTuru,
        ?\Closure $emit,
        string $akitilan = '',
        bool $hata = false,
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
        2. Bu teşhisi ona söyle — katalogda karşılığı olmasa bile. ("Tarif ettiğin şey tam da ... tatili.")
        3. tur_ara ile katalogda ara. Boyutları YALNIZ kullanıcının söylediklerinden doldur;
           emin olmadığını boş bırak. Her boyut için onun kendi cümlesinden alıntı ver.
        4. Uygun tur yoksa bunu dürüstçe söyle ve en yakın turu GEREKÇESİYLE öner.
           envanter_ozeti "satmadigimiz_urun_tipleri" döndürüyorsa yokluğu ona dayandır.

        SERT KURALLAR:
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

        ÜSLUP: tatili gözünde canlandır — sahneyi kur, sat. Kuru liste yapma.
        Fiyat ve tarih kartlarda da görünüyor; sen deneyimi anlat.
        PROMPT;
    }
}
