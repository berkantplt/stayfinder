<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

/**
 * Mevcut kategori adlarını başlık biçimine çeker (Category::normalizeName).
 * Yeni kayıtlar mutator ile zaten düzeliyor; bu komut eski kayıtlar için
 * tek seferliktir. Önce --dry-run ile neyin değişeceğini görün.
 */
class NormalizeCategoryNames extends Command
{
    protected $signature = 'app:normalize-category-names {--dry-run : Yalnız listele, kaydetme}';

    protected $description = 'Küçük harfle girilmiş kategori adlarını başlık biçimine çeker (bali turları → Bali Turları)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (Category::query()->orderBy('id')->get() as $category) {
            $current = (string) $category->getRawOriginal('name');
            $normalized = Category::normalizeName($current);

            if ($normalized === $current) {
                continue;
            }

            $changed++;
            $this->line(sprintf('#%d  "%s"  →  "%s"', $category->id, $current, $normalized));

            if (! $dryRun) {
                $category->name = $normalized;
                $category->saveQuietly();
            }
        }

        $this->info($changed === 0
            ? 'Değişecek kategori adı yok.'
            : ($dryRun ? "{$changed} kategori değişecek (dry-run, kaydedilmedi)." : "{$changed} kategori adı güncellendi."));

        return self::SUCCESS;
    }
}
