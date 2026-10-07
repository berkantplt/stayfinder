<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\Category;
use App\Services\BulkImport\BulkTourImporter;
use Illuminate\Console\Command;

/**
 * Liste dosyasındaki kaynak URL'leri TourUrlImporter'dan geçirip tur olarak kaydeder.
 *
 * Satır biçimi:  acenta-slug;kategori-slug;https://kaynak/tur-sayfasi[;vize]
 *   - vize isteğe bağlı: 0 (vizesiz) | 1 (vizeli) | kapida. Boşsa kural: slug
 *     ipucu ("vizesiz", "vize dahil") > yurt içi vizesiz > kategori varsayılanı.
 *   - # ile başlayan satırlar ve boş satırlar yorumdur.
 *
 * Akış: --check (ağa çıkmaz, listeyi doğrular) → --dry (sayfaları okur, LLM
 * çağrısı YAPAR, yazmaz; sonuç 30 dk önbellekte kalır) → gerçek koşu.
 * Aynı URL o acentada varsa atlanır → yarıda kesilirse yeniden koşmak yeter.
 * --limit kaç yeni tur çekileceğini sınırlar (atlananlar sayılmaz).
 */
class BulkImportTours extends Command
{
    protected $signature = 'app:bulk-import-tours
        {file : Liste dosyası (acenta;kategori;url[;vize])}
        {--batch= : Parti etiketi (zorunlu) — app:bulk-import-agencies ile aynı olmalı}
        {--check : Yalnız listeyi doğrula (ağa çıkmaz, LLM çağrısı yok)}
        {--dry : Sayfaları oku ama kaydetme (LLM çağrısı YAPILIR)}
        {--limit=0 : En fazla bu kadar yeni tur çek (0 = sınırsız)}
        {--sleep=2 : İstekler arası bekleme (sn) — kaynak siteyi yormamak için}
        {--agency= : Yalnız bu acenta slug\'ının satırlarını işle}
        {--report= : Sonuçları bu TSV dosyasına da yaz}';

    protected $description = 'Liste dosyasındaki kaynak URL\'leri içe aktarıp acentalara tur olarak kaydeder (toplu test verisi)';

    public function handle(BulkTourImporter $importer): int
    {
        $batch = self::validateBatch($this->option('batch'), $this);
        if ($batch === null) {
            return self::FAILURE;
        }

        $path = self::resolveInputPath((string) $this->argument('file'));
        if (! is_file($path)) {
            $this->error("Dosya bulunamadı: {$path}");

            return self::FAILURE;
        }

        [$entries, $problems, $warnings] = $this->parseList($path, $this->option('agency'));

        foreach ($problems as $p) {
            $this->error('✗ '.$p);
        }
        foreach ($warnings as $w) {
            $this->warn('! '.$w);
        }
        $perAgency = collect($entries)->groupBy('agency_slug')->map->count();
        $this->info(sprintf('%d satır okundu: %s', count($entries), $perAgency->map(fn ($n, $a) => "{$a} {$n}")->implode(', ')));

        if ($problems !== []) {
            $this->error(count($problems).' sorun var — düzeltmeden içe aktarma başlamaz.');

            return self::FAILURE;
        }
        if ($this->option('check')) {
            $this->info('Liste geçerli.');

            return self::SUCCESS;
        }
        if ($entries === []) {
            $this->warn('İşlenecek satır yok.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry');
        $limit = max(0, (int) $this->option('limit'));
        $sleep = max(0, (int) $this->option('sleep'));
        $report = $this->option('report') ? self::resolveOutputPath((string) $this->option('report')) : null;

        if ($dry) {
            $this->warn('KURU ÇALIŞMA: sayfalar okunur (LLM çağrısı yapılır) ama hiçbir şey yazılmaz. 30 dk içinde gerçek koşu önbellekten yararlanır.');
        } else {
            $this->warn("Turlar '{$batch}' partisiyle KAYDEDİLECEK. Duyuru susturulur; embedding/karakter/rubrik kuyruğa gider (worker çalışıyor olmalı).");
        }

        $reportHandle = null;
        if ($report !== null) {
            $reportHandle = fopen($report, file_exists($report) ? 'a' : 'w');
            if ($reportHandle === false) {
                $this->error("Rapor dosyası açılamadı: {$report}");

                return self::FAILURE;
            }
            if (filesize($report) === 0) {
                fwrite($reportHandle, implode("\t", ['durum', 'acenta', 'kategori', 'url', 'tur_id', 'baslik', 'tarih', 'fiyat', 'para', 'vize', 'gorsel', 'kalkis', 'not'])."\n");
            }
        }

        $counts = [BulkTourImporter::STATUS_CREATED => 0, BulkTourImporter::STATUS_SKIPPED => 0, BulkTourImporter::STATUS_FAILED => 0, BulkTourImporter::STATUS_DRY => 0];
        $attempted = 0;

        foreach ($entries as $entry) {
            if ($limit > 0 && $attempted >= $limit) {
                $this->comment("--limit={$limit} doldu, kalan satırlar bir sonraki koşuya kaldı.");
                break;
            }

            $result = $importer->importOne($entry['url'], $entry['agency'], $entry['category'], $batch, $entry['visa'], $dry);
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;

            $head = sprintf('[%s] %s/%s  %s', $result['status'], $entry['agency_slug'], $entry['category_slug'], $entry['url']);
            if ($result['status'] === BulkTourImporter::STATUS_FAILED) {
                $this->error($head);
                $this->line('    → '.$result['reason']);
            } elseif ($result['status'] === BulkTourImporter::STATUS_SKIPPED) {
                $this->comment($head.'  → '.$result['reason']);
            } else {
                $this->info($head);
                $this->line(sprintf('    "%s"  %d tarih  %s %s  %s  %d görsel  kalkış: %s%s',
                    $result['title'], $result['dates'],
                    $result['price'] !== null ? number_format($result['price'], 0, ',', '.') : '-', $result['currency'] ?? '',
                    $result['visa'] ?? 'vize belirsiz', $result['images'], $result['departure_city'] ?? '-',
                    $result['tour_id'] ? "  #{$result['tour_id']}" : ''));
            }
            foreach ($result['notes'] as $note) {
                $this->line('    · '.$note);
            }
            foreach ($result['warnings'] as $warning) {
                $this->line('    ⚠ '.$warning);
            }

            if ($reportHandle) {
                fwrite($reportHandle, implode("\t", array_map(fn ($v) => str_replace(["\t", "\n", "\r"], ' ', (string) $v), [
                    $result['status'], $entry['agency_slug'], $entry['category_slug'], $entry['url'], $result['tour_id'] ?? '',
                    $result['title'] ?? '', $result['dates'], $result['price'] ?? '', $result['currency'] ?? '', $result['visa'] ?? '',
                    $result['images'], $result['departure_city'] ?? '', trim(($result['reason'] ?? '').' '.implode(' | ', $result['notes'])),
                ]))."\n");
            }

            if ($result['status'] !== BulkTourImporter::STATUS_SKIPPED) {
                $attempted++;
                if ($sleep > 0) {
                    sleep($sleep);
                }
            }
        }

        if ($reportHandle) {
            fclose($reportHandle);
        }

        $this->newLine();
        $this->info(sprintf('Özet: %d eklendi, %d kuru, %d atlandı, %d başarısız.',
            $counts[BulkTourImporter::STATUS_CREATED], $counts[BulkTourImporter::STATUS_DRY],
            $counts[BulkTourImporter::STATUS_SKIPPED], $counts[BulkTourImporter::STATUS_FAILED]));

        return $counts[BulkTourImporter::STATUS_FAILED] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{0: array<int, array{line: int, agency_slug: string, category_slug: string, url: string, visa: ?string, agency: Agency, category: Category}>, 1: string[], 2: string[]}
     */
    private function parseList(string $path, ?string $onlyAgency): array
    {
        $problems = [];
        $warnings = [];
        $raw = [];
        $seenUrls = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
            $no = $i + 1;
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode(';', $line));
            if (count($parts) < 3 || count($parts) > 4) {
                $problems[] = "satır {$no}: 3 veya 4 alan bekleniyor (acenta;kategori;url[;vize])";

                continue;
            }
            [$agencySlug, $categorySlug, $url] = $parts;
            $visa = isset($parts[3]) && $parts[3] !== '' ? strtolower($parts[3]) : null;
            if ($visa !== null && ! in_array($visa, ['0', '1', 'kapida'], true)) {
                $problems[] = "satır {$no}: vize '0', '1' veya 'kapida' olmalı";
            }
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
                $problems[] = "satır {$no}: geçersiz URL";
            }
            if (isset($seenUrls[$url])) {
                $problems[] = "satır {$no}: aynı URL satır {$seenUrls[$url]}'de de var";
            }
            $seenUrls[$url] = $no;
            $raw[] = ['line' => $no, 'agency_slug' => $agencySlug, 'category_slug' => $categorySlug, 'url' => $url, 'visa' => $visa];
        }

        $agencies = Agency::query()->whereIn('slug', array_unique(array_column($raw, 'agency_slug')))->get()->keyBy('slug');
        $categories = Category::query()->whereIn('slug', array_unique(array_column($raw, 'category_slug')))->get()->keyBy('slug');

        $entries = [];
        $accessWarned = [];
        foreach ($raw as $row) {
            $agency = $agencies->get($row['agency_slug']);
            $category = $categories->get($row['category_slug']);
            if (! $agency) {
                $problems[] = "satır {$row['line']}: acenta yok: {$row['agency_slug']} (önce app:bulk-import-agencies)";
            }
            if (! $category) {
                $problems[] = "satır {$row['line']}: kategori yok: {$row['category_slug']}";
            } elseif ($category->parent_id === null) {
                $problems[] = "satır {$row['line']}: {$row['category_slug']} üst kategori — tur alamaz";
            } elseif (! $category->is_active) {
                $problems[] = "satır {$row['line']}: {$row['category_slug']} pasif";
            }
            if (! $agency || ! $category) {
                continue;
            }
            $pair = $agency->slug.'/'.$category->slug;
            if (! isset($accessWarned[$pair]) && ! $agency->hasCategoryAccess($category)) {
                $accessWarned[$pair] = true;
                $warnings[] = "{$pair}: acentanın bu kategoride aboneliği yok — turlar kaydedilir ama yayında GÖRÜNMEZ";
            }
            if ($onlyAgency !== null && $onlyAgency !== '' && $agency->slug !== $onlyAgency) {
                continue;
            }
            $entries[] = $row + ['agency' => $agency, 'category' => $category];
        }

        return [$entries, array_values(array_unique($problems)), $warnings];
    }

    /**
     * Girdi dosyası: önce verildiği gibi (çalışma dizinine göre), yoksa uygulama
     * köküne göre. Plesk "PHP betiği çalıştır" görevi artisan'ı belirsiz bir
     * çalışma dizininden koşturuyor; göreli yol orada kökten çözülmeli.
     */
    public static function resolveInputPath(string $path): string
    {
        if ($path === '' || is_file($path) || str_starts_with($path, '/')) {
            return $path;
        }
        $alt = base_path($path);

        return is_file($alt) ? $alt : $path;
    }

    /** Çıktı dosyası (rapor): göreli yol her zaman uygulama köküne göre. */
    public static function resolveOutputPath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /** Parti etiketi: temizlik ve iz için zorunlu; dosya adı/etiket olarak güvenli olmalı. */
    public static function validateBatch(mixed $batch, Command $command): ?string
    {
        $batch = trim((string) $batch);
        if ($batch === '') {
            $command->error('--batch zorunlu (ör. --batch=toplu-2026-10). Acenta ve tur komutlarında AYNI etiketi kullanın; temizlik bu etiketle yapılır.');

            return null;
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/', $batch)) {
            $command->error('--batch yalnız harf, rakam, tire ve alt çizgi içerebilir (en fazla 60 karakter).');

            return null;
        }

        return $batch;
    }
}
