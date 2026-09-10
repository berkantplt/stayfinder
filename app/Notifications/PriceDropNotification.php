<?php

namespace App\Notifications;

use App\Models\Tour;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Favorilenen turun fiyatı düşünce site içi bildirim (tek kanal: database).
 * Eski ve yeni fiyat, yüzde farkı taşır; aynı gün ikinci düşüşte gözlemci
 * mevcut bildirimi payload() ile günceller, ikinci bildirim açmaz.
 */
class PriceDropNotification extends Notification
{
    use Queueable;

    public $tour;

    public ?float $oldPrice;

    public function __construct($tour, ?float $oldPrice = null)
    {
        $this->tour = $tour;
        $this->oldPrice = $oldPrice;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return self::payload($this->tour, $this->oldPrice);
    }

    /**
     * Bildirim verisi: "4.900 ₺ yerine 4.200 ₺ (%14 düştü)". Eski fiyat yoksa
     * (eski çağrı biçimi) yalnız yeni fiyat yazılır.
     *
     * @return array<string, mixed>
     */
    public static function payload(Tour $tour, ?float $oldPrice): array
    {
        $yeni = (float) $tour->price;
        $sembol = $tour->currency_symbol;
        $fmt = fn (float $v) => number_format($v, 0, ',', '.').' '.$sembol;
        $dusus = $oldPrice !== null && $oldPrice > $yeni;
        $yuzde = $dusus ? (int) round((1 - $yeni / $oldPrice) * 100) : null;

        $message = $dusus
            ? "{$tour->title}: {$fmt($oldPrice)} yerine {$fmt($yeni)}".($yuzde ? " (%{$yuzde} düştü)" : '')
            : "{$tour->title} turunun fiyatı düştü! Yeni fiyat: ".$tour->formatted_price;

        return [
            'tour_id' => $tour->id,
            'title' => 'Fiyat Düştü!',
            'message' => $message,
            'url' => route('tours.show', $tour),
            'icon' => '📉',
            'old_price' => $oldPrice,
            'new_price' => $yeni,
            'percent' => $yuzde,
            'currency' => $tour->currency,
        ];
    }
}
