<?php

namespace App\Console\Commands;

use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Tur slug'larını SEO uyumlu hale getirir.
 *
 * Eski üretim başlığın sonuna rastgele 5 karakter ekliyordu
 * ("kapadokya-turu-a3f9x"); bu son ek anahtar kelimeyi seyreltiyor.
 * Yeni üretim yalnız çakışmada sayısal son ek kullanır ("-2", "-3").
 *
 * Slug'lar bugüne kadar hiçbir URL'de kullanılmadı (rota ID ile çözülüyordu),
 * bu yüzden yeniden üretmek index edilmiş hiçbir adresi kırmaz.
 */
class BackfillTourSlugs extends Command
{
    protected $signature = 'seo:backfill-tour-slugs {--dry-run : Yalnız ne değişeceğini göster, kaydetme}';

    protected $description = 'Tur slug\'larını rastgele son ekten arındırıp SEO uyumlu hale getirir';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;
        $skipped = 0;

        // Aynı başlıktan iki tur varsa ikincisi "-2" almalı; bunu bellekte
        // takip ediyoruz çünkü makeSlug yalnız kaydedilmiş satırlara bakar.
        $taken = [];

        foreach (Tour::orderBy('id')->cursor() as $tour) {
            $base = Str::slug((string) $tour->title);
            $base = $base !== '' ? Str::limit($base, 90, '') : 'tur';

            $slug = $base;
            $suffix = 1;
            while (isset($taken[$slug]) || Tour::where('slug', $slug)->whereKeyNot($tour->getKey())->exists()) {
                $suffix++;
                $slug = $base.'-'.$suffix;
            }
            $taken[$slug] = true;

            if ($slug === $tour->slug) {
                $skipped++;

                continue;
            }

            $this->line(sprintf('  #%d  %s  →  %s', $tour->id, $tour->slug ?: '(boş)', $slug));

            if (! $dryRun) {
                // saving/updating event'leri (fiyat geçmişi, price_try) tetiklenmesin:
                // yalnız slug kolonu değişiyor.
                Tour::whereKey($tour->getKey())->update(['slug' => $slug]);
            }

            $changed++;
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d tur güncellendi, %d tur zaten doğruydu.',
            $dryRun ? '[KURU ÇALIŞMA] ' : '',
            $changed,
            $skipped
        ));

        if (! $dryRun && $changed > 0) {
            cache()->forget('sitemap_xml');
            $this->line('Sitemap cache temizlendi.');
        }

        return self::SUCCESS;
    }
}
