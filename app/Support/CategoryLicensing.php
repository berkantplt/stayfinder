<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class CategoryLicensing
{
    private static ?bool $schemaReady = null;

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

    public static function resetCache(): void
    {
        self::$schemaReady = null;
    }
}
