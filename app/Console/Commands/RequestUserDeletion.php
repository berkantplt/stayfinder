<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Console\Command;

/**
 * D4 — Yönetici tarafı: KVKK talebi gelen müşteri hesabı için silme sürecini
 * başlatır (30 gün bekleme + anonimleştirme) ya da iptal eder. Admin panelinde
 * kullanıcı yönetimi ekranı olmadığı (B2) için komut olarak sunulur.
 */
class RequestUserDeletion extends Command
{
    protected $signature = 'users:request-deletion {email : Müşteri e-postası} {--cancel : Bekleyen talebi iptal et}';

    protected $description = 'Müşteri hesabı için silme talebi başlatır/iptal eder (KVKK)';

    public function handle(AccountDeletionService $service): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Kullanıcı bulunamadı.');

            return self::FAILURE;
        }

        if (! $user->isCustomer()) {
            $this->error('Yalnız müşteri hesapları bu yoldan silinir (acenta/admin hesapları değil).');

            return self::FAILURE;
        }

        if ($this->option('cancel')) {
            $service->cancel($user);
            $this->info('Silme talebi iptal edildi: '.$user->email);

            return self::SUCCESS;
        }

        $service->request($user);
        $this->info(sprintf('Silme talebi alındı: %s — %d gün sonra users:purge-deleted ile anonimleştirilir.', $user->email, AccountDeletionService::WAITING_DAYS));

        return self::SUCCESS;
    }
}
