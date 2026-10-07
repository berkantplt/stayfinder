<?php

namespace App\Console\Commands;

use App\Services\BulkImport\AgencyProvisioner;
use App\Services\BulkImport\BulkImportException;
use Illuminate\Console\Command;

/**
 * Toplu içe aktarım için acenta hesaplarını kurar (JSON tanımından): acenta +
 * panel kullanıcısı + kategori başına manuel abonelik (varsayılan 12 ay, +8 tur hakkı).
 *
 * Örnek: php artisan app:bulk-import-agencies database/data/bulk-import/acentalar.json --batch=toplu-2026-10
 *
 * E-POSTA GÖNDERMEZ (panel adresleri uydurma). Yeniden koşulabilir: var olan
 * acenta/kullanıcı/aktif abonelik dokunulmaz. Parola verilmezse her yeni
 * kullanıcı için rastgele üretilir ve YALNIZ bu çıktıda gösterilir — not alın.
 */
class BulkImportAgencies extends Command
{
    protected $signature = 'app:bulk-import-agencies
        {file : Acenta tanımları (JSON dizi veya {"agencies": [...]})}
        {--batch= : Parti etiketi (zorunlu, ör. toplu-2026-10) — temizlik bu etiketle yapılır}
        {--dry : Hiçbir şey yazma, ne yapılacağını göster}
        {--password= : Yeni panel kullanıcılarına ortak parola (boşsa rastgele üretilir)}';

    protected $description = 'Toplu içe aktarım için acenta hesaplarını, panel kullanıcılarını ve 1 yıllık kategori aboneliklerini kurar';

    public function handle(AgencyProvisioner $provisioner): int
    {
        $batch = BulkImportTours::validateBatch($this->option('batch'), $this);
        if ($batch === null) {
            return self::FAILURE;
        }

        $path = BulkImportTours::resolveInputPath((string) $this->argument('file'));
        if (! is_file($path)) {
            $this->error("Dosya bulunamadı: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $definitions = is_array($decoded) ? ($decoded['agencies'] ?? $decoded) : null;
        if (! is_array($definitions) || $definitions === []) {
            $this->error('JSON okunamadı veya boş: bir acenta dizisi bekleniyor.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry');
        $password = $this->option('password') !== null ? (string) $this->option('password') : null;
        if ($password !== null && mb_strlen($password) < 8) {
            $this->error('Parola en az 8 karakter olmalı.');

            return self::FAILURE;
        }

        if ($dry) {
            $this->warn('KURU ÇALIŞMA: hiçbir şey yazılmayacak.');
        }

        $rows = [];
        $failed = 0;
        foreach ($definitions as $i => $definition) {
            $label = is_array($definition) ? (string) ($definition['slug'] ?? $definition['name'] ?? "#{$i}") : "#{$i}";
            try {
                $result = $provisioner->provision(is_array($definition) ? $definition : [], $batch, $password, $dry);
            } catch (BulkImportException $e) {
                $failed++;
                $this->error("✗ {$label}: ".$e->getMessage());

                continue;
            }

            $subs = collect($result['subscriptions'])->map(fn ($s, $slug) => "{$slug}: {$s}")->implode("\n");
            $rows[] = [
                $label,
                $result['agency_status'],
                $result['user_status'],
                $result['password'] ?? ($result['user_status'] === 'yaratıldı' && $password !== null ? '(verilen)' : '-'),
                $subs,
            ];
        }

        if ($rows !== []) {
            $this->table(['Acenta', 'Acenta durumu', 'Panel kullanıcısı', 'Parola', 'Abonelikler'], $rows);
        }

        $ok = count($rows);
        $this->info($dry
            ? "{$ok} acenta planlandı, {$failed} hata (kuru çalışma)."
            : "{$ok} acenta işlendi, {$failed} hata. Parti: {$batch}");

        if (! $dry && collect($rows)->contains(fn ($r) => $r[3] !== '-' && $r[3] !== '(verilen)')) {
            $this->warn('Üretilen parolalar bir daha gösterilmez — şimdi not alın.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
