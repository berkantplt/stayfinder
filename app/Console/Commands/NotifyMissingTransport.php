<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Models\User;
use App\Notifications\MissingTransportNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ulaşım bilgisi eksik turları olan acentalara hatırlatma gönderir.
 *
 * Kaynak URL'si olmayan turlar app:reimport-tours ile doldurulamıyor — tek yol
 * acentanın panelden girmesi. Bu komut onları uyarır.
 *
 * Acenta başına TEK bildirim (tur başına değil). Tekrar çalıştırılabilir ama
 * --once ile son 30 günde bildirim almış acentalar atlanır; zamanlanmış göreve
 * bağlanacaksa o bayrak kullanılmalı, yoksa her koşuda tekrar bildirim düşer.
 */
class NotifyMissingTransport extends Command
{
    protected $signature = 'app:notify-missing-transport
        {--dry : Kime gideceğini listele, gönderme}
        {--once : Son 30 günde bu bildirimi almış acentaları atla}';

    protected $description = 'Ulaşım bilgisi eksik turları olan acentalara bildirim gönderir.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $eksik = Tour::query()
            ->whereNull('transport_type')
            ->where('is_active', true)
            ->with('agency')
            ->get()
            ->groupBy('agency_id');

        if ($eksik->isEmpty()) {
            $this->info('Ulaşım bilgisi eksik aktif tur yok.');

            return self::SUCCESS;
        }

        $this->info("Ulaşım bilgisi eksik turu olan acenta: {$eksik->count()}");
        $this->newLine();

        $gonderilen = 0;
        $atlanan = 0;

        foreach ($eksik as $agencyId => $turlar) {
            $agency = $turlar->first()->agency;
            if (! $agency || ! $agency->is_active) {
                continue;
            }

            $kullanicilar = User::where('agency_id', $agencyId)->get();
            if ($kullanicilar->isEmpty()) {
                $this->line("  · {$agency->name} — panel kullanıcısı yok, atlandı");

                continue;
            }

            if ($this->option('once') && $this->yakindaBildirildi($kullanicilar->pluck('id')->all())) {
                $atlanan++;
                $this->line("  · {$agency->name} — son 30 günde bildirilmiş, atlandı");

                continue;
            }

            $this->line("  ✓ {$agency->name} — {$turlar->count()} tur, {$kullanicilar->count()} kullanıcı");

            if (! $dry) {
                Notification::send($kullanicilar, new MissingTransportNotification(
                    $turlar->count(),
                    $turlar->pluck('title')->all()
                ));
            }
            $gonderilen++;
        }

        $this->newLine();
        $this->info("Bildirim gönderilen acenta: {$gonderilen}".($atlanan ? "   Atlanan: {$atlanan}" : ''));

        if ($dry) {
            $this->comment('Kuru çalışmaydı — göndermek için --dry olmadan çalıştırın.');
        }

        return self::SUCCESS;
    }

    /** @param  array<int, int>  $userIds */
    private function yakindaBildirildi(array $userIds): bool
    {
        return DB::table('notifications')
            ->where('type', MissingTransportNotification::class)
            ->whereIn('notifiable_id', $userIds)
            ->where('notifiable_type', User::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();
    }
}
