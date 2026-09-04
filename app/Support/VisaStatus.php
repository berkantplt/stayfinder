<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turun vize durumu — ÜÇ DEĞER, iki kolon.
 *
 * KAYNAK: tours.requires_visa + tours.visa_on_arrival, yani acentanın formda
 * işaretlediği beyan. DestinationProfile.requires_visa_for_tr'ye BAĞLANMAZ:
 * o alan LLM'in dünya bilgisi ve hatalı olduğu doğrulandı (Yunanistan "vize
 * gerekmiyor" dönüyor). Aynı gerekçe kıyas ekranında da yazılı
 * (TourComparison: "taşıdığımız iddia bizim değil acentanın").
 *
 * KAPIDA VİZE AYRI BİR DEĞER, "vizeli"nin içinde değil: yolcu için işi bambaşka
 * (konsolosluk randevusu ve evrak yok, sınırda ödeniyor). Vizesiz de sayılmaz —
 * yine de vize alınıyor.
 *
 * ÜÇÜNCÜ durum "beyan edilmemiş" (null): 2026-09-01'den beri form alanı zorunlu,
 * null yalnız eski kayıtlarda kalabilir. null "vizesiz" diye OKUNMAZ; hem
 * filtrelerde elenir hem de sohbette "bilmiyorum" olarak geçer.
 */
final class VisaStatus
{
    public const VIZESIZ = 'vizesiz';

    public const KAPIDA = 'kapida';

    public const VIZELI = 'vizeli';

    /** Sohbet aracının enum'u ve filtrelerin kabul ettiği tek değer kümesi. */
    public const DEGERLER = [self::VIZESIZ, self::KAPIDA, self::VIZELI];

    /** İki kolon → tek kod. Beyan yoksa null (asla "vizesiz" değil). */
    public static function kod(?bool $requiresVisa, ?bool $kapida): ?string
    {
        return match (true) {
            $requiresVisa === null => null,
            $requiresVisa && $kapida === true => self::KAPIDA,
            $requiresVisa => self::VIZELI,
            default => self::VIZESIZ,
        };
    }

    public static function turKodu(Tour $tour): ?string
    {
        return self::kod($tour->requires_visa, $tour->visa_on_arrival);
    }

    /** Kullanıcıya gösterilecek metin — kıyas ekranındaki sözcüklerle aynı. */
    public static function etiket(?string $kod): ?string
    {
        return match ($kod) {
            self::VIZESIZ => 'Vize gerekmiyor',
            self::KAPIDA => 'Kapıda vize',
            self::VIZELI => 'Vize gerekiyor',
            default => null,
        };
    }

    public static function gecerliMi(mixed $kod): bool
    {
        return is_string($kod) && in_array($kod, self::DEGERLER, true);
    }

    /**
     * Sorguyu tek bir vize durumuna daraltır. Tanınmayan kod SESSİZCE yok sayılır:
     * değer modelden geliyor, uydurma bir kod aramayı boşaltmasın.
     *
     * "vizeli" dalında visa_on_arrival'ın null'ı da sayılır — eski kayıtlarda
     * bayrak boş ama requires_visa=true, kıyas ekranı da bunu "Vize gerekiyor"
     * diye okuyor.
     */
    public static function filtrele(Builder $query, mixed $kod): void
    {
        match ($kod) {
            self::VIZESIZ => $query->where('requires_visa', false),
            self::KAPIDA => $query->where('requires_visa', true)->where('visa_on_arrival', true),
            self::VIZELI => $query->where('requires_visa', true)
                ->where(fn ($q) => $q->where('visa_on_arrival', false)->orWhereNull('visa_on_arrival')),
            default => null,
        };
    }
}
