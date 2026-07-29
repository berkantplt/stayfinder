<?php

namespace App\Http\Controllers;

use App\Services\AiSearch\PiiMasker;
use App\Services\Chat\ChatAgent;
use App\Services\Chat\ConversationState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chatbot v2 uçları. İnce controller: doğrula → akıt → oturuma yaz.
 *
 * HAFIZA (karar 2): konuşma ve durum YALNIZ oturumda tutulur, veritabanına
 * yazılmaz. Sekme kapanınca unutulur — kalıcı profil yok, KVKK yükü düşük.
 */
class ChatV2Controller extends Controller
{
    private const OTURUM_ANAHTARI = 'chat_v2';

    /** Bağlam penceresi: son N tur. Yapısal durum zaten ayrı taşınıyor. */
    private const GECMIS_LIMITI = 12;

    public function __construct(private readonly ChatAgent $agent) {}

    /** SSE akışı. İskelet v1'den birebir taşındı (nginx altında kazanılmış ayarlar). */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        // PII maskeleme v1'den korunuyor: kart/TC numarası ne oturuma ne LLM'e gider
        $mesaj = PiiMasker::mask(trim($validated['message']))['text'];

        $oturum = (array) $request->session()->get(self::OTURUM_ANAHTARI, []);
        $gecmis = array_slice((array) ($oturum['gecmis'] ?? []), -self::GECMIS_LIMITI);
        $durum = ConversationState::fromArray($oturum['durum'] ?? null);

        return response()->stream(function () use ($request, $mesaj, $gecmis, $durum) {
            // İstemci koparsa bile oturum yazımı tamamlansın
            @ignore_user_abort(true);

            // Sunucu tarafı buffering'i kapat (FastCGI/PHP-FPM'de kritik).
            // Testte ATLANIR: test koşucusu akışı kendi çıktı tamponundan
            // okuyor, tamponları yıkmak onu bozuyor. Atlanan kısım ortam
            // ayarı; olay/başlık mantığı testlerde aynen doğrulanıyor.
            if (! app()->runningUnitTests()) {
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', '1');
                }
                @ini_set('zlib.output_compression', '0');
                @ini_set('output_buffering', 'off');
                @ini_set('implicit_flush', '1');
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                @ob_implicit_flush(true);
            }

            $emit = function (string $event, $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
                @flush();
            };

            // İlk baytı hemen akıt: araç turları çıktı üretmeden önce nginx
            // proxy_read_timeout sıfır-bayt görüp bağlantıyı koparmasın
            echo ": keep-alive\n\n";
            @flush();

            try {
                $sonuc = $this->agent->handle(
                    $mesaj,
                    $gecmis,
                    $durum,
                    fn (string $parca) => $emit('delta', ['text' => $parca]),
                    // Araçlar koşarken ne yaptığını göster — sessizlik hem kötü
                    // deneyim hem istemci bekçisini tetikliyordu
                    fn (string $metin) => $emit('faz', ['text' => $metin]),
                );

                if ($sonuc['turlar'] !== []) {
                    $emit('tours', ['items' => $sonuc['turlar']]);
                }

                // Oturuma yaz: kullanıcının GÖRDÜĞÜ metin geçmişe girer, yoksa
                // model bir sonraki turda kendi söylediğini bilmez
                $gecmis[] = ['role' => 'user', 'content' => $mesaj];
                $gecmis[] = ['role' => 'assistant', 'content' => $sonuc['metin']];

                $request->session()->put(self::OTURUM_ANAHTARI, [
                    'gecmis' => array_slice($gecmis, -self::GECMIS_LIMITI),
                    'durum' => $sonuc['durum']->toArray(),
                ]);
                $request->session()->save();

                $emit('done', ['is_error' => (bool) $sonuc['hata']]);
            } catch (\Throwable $e) {
                // Ham exception mesajı kullanıcıya SIZMASIN
                Log::error('[ChatV2] stream hata: '.$e->getMessage());
                $emit('error', ['message' => 'Şu an bir sorun oluştu, mesajını tekrar gönderir misin?']);
                $emit('done', ['is_error' => true]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /** Konuşmayı sıfırla (oturumluk hafızayı temizler). */
    public function reset(Request $request)
    {
        $request->session()->forget(self::OTURUM_ANAHTARI);

        return response()->json(['ok' => true]);
    }
}
