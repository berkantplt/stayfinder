<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use Tests\TestCase;

class AiInternationalIntentTest extends TestCase
{
    private function detect(string $query): ?bool
    {
        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'detectInternationalIntent');

        return $reflection->invoke($controller, $query);
    }

    public function test_explicit_yurt_disi_returns_true(): void
    {
        $this->assertTrue($this->detect('yurt dışına çıkmak istiyorum'));
        $this->assertTrue($this->detect('Yurt dışı tatil'));
        $this->assertTrue($this->detect('YURTDIŞI olsun'));
    }

    public function test_explicit_yurt_ici_returns_false(): void
    {
        $this->assertFalse($this->detect('yurt içi tatil'));
        $this->assertFalse($this->detect('Türkiye içinde'));
        $this->assertFalse($this->detect('ülke içi olsun'));
    }

    public function test_known_foreign_cities_return_true(): void
    {
        foreach (['paris', 'roma', 'maldivler', 'bali', 'mısır', 'londra', 'tokyo'] as $place) {
            $this->assertTrue($this->detect($place . ' düşünüyorum'), "Failed for: {$place}");
        }
    }

    public function test_known_domestic_cities_return_false(): void
    {
        foreach (['rize', 'antalya', 'bodrum', 'kapadokya', 'istanbul', 'fethiye'] as $place) {
            $this->assertFalse($this->detect($place . ' tatili'), "Failed for: {$place}");
        }
    }

    public function test_continents_and_regions_return_true(): void
    {
        $this->assertTrue($this->detect('avrupa turu'));
        $this->assertTrue($this->detect('asya gezisi'));
        $this->assertTrue($this->detect('ortadoğu turu'));
    }

    public function test_ambiguous_query_returns_null(): void
    {
        $this->assertNull($this->detect('tatil yapmak istiyorum'));
        $this->assertNull($this->detect('5 gün gezi'));
    }
}
