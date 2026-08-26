<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class CategoryLicensing
{
    /** Her kategori aboneliğine dahil tur ekleme hakkı; fazlası ekstra hak olarak satılır. */
    public const BASE_TOUR_ALLOWANCE = 2;

    private static ?bool $schemaReady = null;

    private static ?bool $slotSchemaReady = null;

    private static ?bool $autoRenewSchemaReady = null;

    public static function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        return self::$schemaReady =
            Schema::hasTable('agency_category_subscriptions')
            && Schema::hasTable('agency_category_orders')
            && Schema::hasTable('agency_category_order_items')
            && Schema::hasColumn('agencies', 'legacy_category_access')
            && Schema::hasColumn('categories', 'monthly_price');
    }

    /**
     * Tur hakkı (slot) kolonları ayrı migration ile geldi; slot şeması eksikken
     * limit uygulanmaz ve ekstra hak satışı kapalı kalır — ana lisans sistemi
     * bundan etkilenmez (schemaReady'ye EKLEME, lisansın tamamını kapatır).
     */
    public static function slotSchemaReady(): bool
    {
        if (self::$slotSchemaReady !== null) {
            return self::$slotSchemaReady;
        }

        return self::$slotSchemaReady = self::schemaReady()
            && Schema::hasColumn('categories', 'extra_tour_price')
            && Schema::hasColumn('agency_category_subscriptions', 'extra_tour_slots')
            && Schema::hasColumn('agency_category_order_items', 'item_type');
    }

    /**
     * Otomatik yenileme + iptal kolonları da ayrı migration'la geldi; aynı
     * kademeli guard kalıbı — eksikken lisans ve slot sistemleri etkilenmez.
     */
    public static function autoRenewSchemaReady(): bool
    {
        if (self::$autoRenewSchemaReady !== null) {
            return self::$autoRenewSchemaReady;
        }

        return self::$autoRenewSchemaReady = self::slotSchemaReady()
            && Schema::hasColumn('agency_category_subscriptions', 'auto_renew')
            && Schema::hasColumn('agency_category_subscriptions', 'next_extra_tour_slots')
            && Schema::hasColumn('agency_category_orders', 'auto_renewal')
            && Schema::hasTable('agency_stored_cards');
    }

    /**
     * Fiili otomatik çekim açık mı: şema + IYZICO_AUTO_RENEW bayrağı.
     * Bayrak kapalıyken iptal/kart altyapısı kodda durur ama UI gizli,
     * yenileme komutu çalışmaz — sistem hatırlatma+manuel yenilemede kalır.
     */
    public static function autoRenewEnabled(): bool
    {
        return self::autoRenewSchemaReady() && (bool) config('iyzico.auto_renew_enabled');
    }

    public static function resetCache(): void
    {
        self::$schemaReady = null;
        self::$slotSchemaReady = null;
        self::$autoRenewSchemaReady = null;
    }
}
