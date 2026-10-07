<?php

namespace App\Support;

/**
 * Saklanan fiyat matrisi ([{dates, packages:[{hotel, prices:{type:{old,new}}}]}])
 * üzerinde onarım yardımcıları. Kaynak sitelerin aynı tabloyu mobil + masaüstü
 * için iki kez basması, içe aktarıcının eski sürümünde kaymış bir KOPYA paket
 * bırakıyordu (Malitur: "Bölge Otelleri" ×2, ikincisinde tek kişilik oda
 * eski/yeni diye yanlış okunmuş). Ayrıştırıcı düzeltildi; bu sınıf daha önce
 * kaydedilmiş turları aynı kuralla temizler (app:bulk-import-dedupe-packages).
 */
class PricingBlocks
{
    /**
     * Aynı blokta aynı otel adı + aynı "iki kişilik oda" fiyatı taşıyan sonraki
     * paketler kopyadır → atılır, ilk (mobil dikey listeden gelen, doğru) kalır.
     * Otel adı farklı ya da double_pp farklıysa gerçekten ayrı pakettir, korunur.
     *
     * @param  array<int, array{dates?: array, packages?: array}>|null  $blocks
     * @return array{blocks: array|null, dropped: int}
     */
    public static function dropDuplicatePackages(?array $blocks): array
    {
        if (! is_array($blocks) || $blocks === []) {
            return ['blocks' => $blocks, 'dropped' => 0];
        }

        $dropped = 0;
        foreach ($blocks as $bi => $block) {
            $packages = is_array($block['packages'] ?? null) ? $block['packages'] : [];
            $kept = [];
            $seen = [];
            foreach ($packages as $pkg) {
                if (! is_array($pkg)) {
                    continue;
                }
                $key = self::fold((string) ($pkg['hotel'] ?? '')).'|'.self::adultKey($pkg);
                if ($key !== '|' && isset($seen[$key])) {
                    $dropped++;

                    continue;
                }
                $seen[$key] = true;
                $kept[] = $pkg;
            }
            $blocks[$bi]['packages'] = array_values($kept);
        }

        return ['blocks' => $blocks, 'dropped' => $dropped];
    }

    /** Kopya tespitinde kullanılan yetişkin fiyat imzası (double_pp yoksa single). */
    private static function adultKey(array $pkg): string
    {
        foreach (['double_pp', 'single'] as $type) {
            $new = $pkg['prices'][$type]['new'] ?? null;
            if ($new !== null && $new !== '') {
                return $type.':'.number_format((float) $new, 2, '.', '');
            }
        }

        return '';
    }

    private static function fold(string $value): string
    {
        $value = strtr(trim($value), ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c', 'Â' => 'a', 'â' => 'a']);

        return preg_replace('/\s+/u', ' ', mb_strtolower($value, 'UTF-8')) ?? '';
    }
}
