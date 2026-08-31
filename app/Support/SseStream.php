<?php

namespace App\Support;

/**
 * SSE (Server-Sent Events) akış iskeleti — nginx altında kazanılmış ayarlar.
 *
 * Ortam kurulumu + olay yazıcı ($emit) üretimi tek yerde: ChatV2Controller ve
 * AiSearchController'daki ikiz kurulumların ortak kaynağı. Farklılıklar
 * parametreyle verilir; davranış değişikliği buradan değil, çağırandan gelir.
 */
final class SseStream
{
    /**
     * SSE yanıt başlıkları. X-Accel-Buffering=no olmazsa nginx akışı biriktirip
     * tek seferde düşürür.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    /**
     * Akış ortamını kurar, ilk baytı akıtır ve $emit closure'ını döndürür.
     *
     * İlk bayt (": keep-alive" yorum satırı) hemen gider: LLM/araç turları çıktı
     * üretmeden önce nginx proxy_read_timeout sıfır-bayt görüp bağlantıyı
     * koparmasın. SSE yorum satırı spec gereği istemcilerce yok sayılır.
     *
     * @param  bool  $testteOrtamKurulumunuAtla  Sunucu tamponu ayarları testte atlanır:
     *                                           test koşucusu akışı kendi çıktı tamponundan
     *                                           okuyor, tamponları yıkmak onu bozuyor.
     *                                           Olay/başlık mantığı testlerde aynen doğrulanır.
     * @param  ?int  $retryMs  Verilirse istemciye SSE "retry" direktifi yazılır
     *                         (yeniden bağlanma bekleme süresi, ms).
     * @return \Closure(string, mixed): void  fn (string $event, $data) — olay yazıcı
     */
    public static function baslat(bool $testteOrtamKurulumunuAtla = true, ?int $retryMs = null): \Closure
    {
        // İstemci koparsa bile akış sonrası yazımlar (oturum/DB) tamamlansın
        @ignore_user_abort(true);

        // Sunucu tarafı buffering'i kapat (FastCGI/PHP-FPM'de kritik)
        if (! ($testteOrtamKurulumunuAtla && app()->runningUnitTests())) {
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

        echo ": keep-alive\n\n";
        if ($retryMs !== null) {
            echo 'retry: '.$retryMs."\n\n";
        }
        @flush();

        return function (string $event, $data): void {
            echo "event: {$event}\n";
            echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
            @flush();
        };
    }
}
