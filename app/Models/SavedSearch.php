<?php

namespace App\Models;

use App\Support\TourListFilter;
use App\Support\TurkishMonths;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Üyenin kaydettiği /turlar filtre kombinasyonu. params yalnız TourListFilter
 * gruplarındaki alanları taşır (sayfa, sıralama vb. atılır).
 */
class SavedSearch extends Model
{
    protected $fillable = ['user_id', 'name', 'params', 'last_checked_at', 'last_notified_at'];

    protected $casts = [
        'params' => 'array',
        'last_checked_at' => 'datetime',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Sonuç sayfası adresi (aynı filtrelerle /turlar). */
    public function url(): string
    {
        return route('tours.index', $this->params);
    }

    /**
     * İstekten yalnız filtre alanlarını al (boşlar atılır, sayfa/sıralama girmez).
     *
     * @return array<string, string>
     */
    public static function paramsFrom(array $input): array
    {
        $alanlar = array_merge(...array_values(TourListFilter::GROUPS));
        $temiz = [];
        foreach ($alanlar as $alan) {
            $v = $input[$alan] ?? null;
            if ($v === null || is_array($v) || trim((string) $v) === '') {
                continue;
            }
            $temiz[$alan] = trim((string) $v);
        }
        ksort($temiz);

        return $temiz;
    }

    /** Okunur ad: "Kapadokya · İstanbul kalkışlı · 19 Eyl–30 Eyl · 5.000–9.000 ₺". */
    public static function nameFor(array $p): string
    {
        $parca = [];
        if (! empty($p['q'])) {
            $parca[] = '“'.$p['q'].'”';
        }
        if (! empty($p['destination'])) {
            $parca[] = $p['destination'];
        }
        if (! empty($p['category'])) {
            $parca[] = Category::where('slug', $p['category'])->value('name') ?? $p['category'];
        }
        if (! empty($p['departure_city'])) {
            $parca[] = $p['departure_city'].' kalkışlı';
        }
        if (! empty($p['date_start']) || ! empty($p['date_end'])) {
            $fmt = fn (?string $d) => $d ? Carbon::parse($d)->locale('tr')->isoFormat('D MMM') : '';
            $parca[] = trim($fmt($p['date_start'] ?? null).'–'.$fmt($p['date_end'] ?? null), '–');
        }
        if (! empty($p['min_days']) || ! empty($p['max_days'])) {
            $parca[] = ($p['min_days'] ?? '1').'–'.($p['max_days'] ?? '∞').' gün';
        }
        if (! empty($p['min_price']) || ! empty($p['max_price'])) {
            $tl = fn ($v) => $v ? number_format((int) $v, 0, ',', '.') : '';
            $parca[] = trim($tl($p['min_price'] ?? null).'–'.$tl($p['max_price'] ?? null), '–').' ₺';
        }
        if (! empty($p['yurt'])) {
            $parca[] = $p['yurt'] === 'dis' ? 'Yurt dışı' : 'Yurt içi';
        }
        if (! empty($p['visa'])) {
            $parca[] = $p['visa'] === 'vizesiz' ? 'Vizesiz' : 'Vizeli';
        }

        return $parca ? mb_substr(implode(' · ', $parca), 0, 160) : 'Tüm turlar';
    }
}
