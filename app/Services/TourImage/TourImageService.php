<?php

namespace App\Services\TourImage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tur görsellerini saklar: yüklenen dosyalar + URL'den indirilen uzak görseller.
 * Her formatı kabul eder (finfo ile image/* doğrular); SSRF korumalıdır.
 */
class TourImageService
{
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

    /** URL'den inen görselde kısa kenar alt sınırı (px) — thumbnail/placeholder savunması */
    private const MIN_DIMENSION = 300;

    private const DISK = 'public';

    private const DIR = 'tours';

    /** İçerik tipi → uzantı (uzantı URL'den/dosyadan çıkmazsa) */
    private const EXT_BY_MIME = [
        'image/jpeg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif',
        'image/heic' => 'heic', 'image/heif' => 'heif', 'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp', 'image/tiff' => 'tiff', 'image/svg+xml' => 'svg',
    ];

    /**
     * Yüklenen dosyayı kaydeder, /storage/... yolunu döner.
     *
     * @throws RuntimeException görsel değilse/çok büyükse
     */
    public function storeUpload(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw new RuntimeException('Dosya yüklenemedi.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RuntimeException('Görsel 20MB sınırını aşıyor.');
        }

        $mime = (string) $file->getMimeType();
        if (! str_starts_with($mime, 'image/')) {
            throw new RuntimeException('Sadece görsel dosyaları kabul edilir.');
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if ($ext === '') {
            $ext = self::EXT_BY_MIME[$mime] ?? 'img';
        }

        $name = self::DIR.'/'.Str::uuid()->toString().'.'.$ext;
        Storage::disk(self::DISK)->putFileAs(self::DIR, $file, basename($name));

        return '/storage/'.$name;
    }

    /**
     * Uzak görseli (URL) güvenli şekilde indirir ve kaydeder; /storage/... döner.
     * Hata olursa null (içe aktarmada tek bir görsel patlarsa diğerleri devam etsin).
     */
    public function downloadAndStore(string $url): ?string
    {
        $url = trim($url);

        // Zaten bizde kayıtlı yerel görsel: olduğu gibi koru
        if (str_starts_with($url, '/storage/')) {
            return Storage::disk(self::DISK)->exists(str_replace('/storage/', '', $url)) ? $url : null;
        }

        if (! $this->isSafeRemoteUrl($url)) {
            return null;
        }

        try {
            // SSRF: her yönlendirme hedefini yeniden doğrula. Guzzle otomatik
            // redirect'i KAPALI; güvenli görünen URL 302 ile iç ağa kaçamaz.
            $hop = 0;
            do {
                if (! $this->isSafeRemoteUrl($url)) {
                    return null;
                }
                $response = Http::timeout(20)
                    ->withoutRedirecting()
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                        'Accept' => 'image/*,*/*;q=0.8',
                    ])
                    ->get($url);

                $status = $response->status();
                if ($status < 300 || $status >= 400) {
                    break;
                }
                $location = trim((string) $response->header('Location'));
                if ($location === '' || $hop >= 4) {
                    return null;
                }
                // Göreli Location'ı mutlaklaştır
                if (! preg_match('#^https?://#i', $location)) {
                    $p = parse_url($url);
                    $sch = $p['scheme'] ?? 'https';
                    $h = $p['host'] ?? '';
                    $pt = isset($p['port']) ? ':'.$p['port'] : '';
                    if (str_starts_with($location, '//')) {
                        $location = $sch.':'.$location;
                    } elseif (str_starts_with($location, '/')) {
                        $location = $sch.'://'.$h.$pt.$location;
                    } else {
                        $path = $p['path'] ?? '/';
                        $location = $sch.'://'.$h.$pt.substr($path, 0, (int) strrpos($path, '/') + 1).$location;
                    }
                }
                $url = $location;
                $hop++;
            } while (true);

            if (! $response->ok()) {
                return null;
            }

            $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            if (! str_starts_with($mime, 'image/')) {
                return null;
            }
            // Uzak SVG galeriye girmez: vektör/logo malzemesidir + PHP sürümüne göre
            // boyut kapısından tutarsız geçerdi (8.5 çözer, 8.2 çözmez) + XSS yüzeyi
            if ($mime === 'image/svg+xml') {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            // Boyut kapısı: thumbnail/tema placeholder'ları galeriye giremesin.
            // getimagesize'ın çözemediği egzotik formatlara (heic/heif/avif) dokunma.
            $dims = @getimagesizefromstring($body);
            if ($dims !== false && min($dims[0], $dims[1]) < self::MIN_DIMENSION) {
                return null;
            }
            if ($dims === false && ! in_array($mime, ['image/heic', 'image/heif', 'image/avif'], true)) {
                return null;
            }

            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
            if ($ext === '' || strlen($ext) > 5) {
                $ext = self::EXT_BY_MIME[$mime] ?? 'img';
            }

            $name = self::DIR.'/'.Str::uuid()->toString().'.'.$ext;
            Storage::disk(self::DISK)->put($name, $body);

            return '/storage/'.$name;
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(string $path): void
    {
        if (str_starts_with($path, '/storage/')) {
            Storage::disk(self::DISK)->delete(str_replace('/storage/', '', $path));
        }
    }

    /**
     * SSRF koruması: sadece http/https + private/reserved IP reddi.
     */
    private function isSafeRemoteUrl(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];
        if (strcasecmp($host, 'localhost') === 0) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}
