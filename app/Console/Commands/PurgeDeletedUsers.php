<?php

namespace App\Console\Commands;

use App\Services\Account\AccountDeletionService;
use Illuminate\Console\Command;

/**
 * D4 — Silme talebi 30 günü dolmuş müşteri hesaplarını anonimleştirir.
 * Zamanlayıcı her gece çalıştırır; --dry-run yalnız listeler.
 */
class PurgeDeletedUsers extends Command
{
    protected $signature = 'users:purge-deleted {--dry-run : Yalnız listele, değiştirme}';

    protected $description = 'Silme talebi 30 günü dolmuş hesapları anonimleştirir (KVKK)';

    public function handle(AccountDeletionService $service): int
    {
        $users = $service->dueUsers()->get();

        if ($users->isEmpty()) {
            $this->info('Süresi dolmuş silme talebi yok.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $this->line(sprintf('#%d %s (talep: %s)%s', $user->id, $user->email, $user->deletion_requested_at->format('d.m.Y'), $this->option('dry-run') ? ' — dry-run' : ''));

            if (! $this->option('dry-run')) {
                $service->anonymize($user);
            }
        }

        $this->info($users->count().' hesap '.($this->option('dry-run') ? 'listelendi.' : 'anonimleştirildi.'));

        return self::SUCCESS;
    }
}
