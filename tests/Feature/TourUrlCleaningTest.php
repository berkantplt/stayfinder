<?php

namespace Tests\Feature;

use App\Http\Controllers\Agency\TourController;
use Tests\TestCase;

class TourUrlCleaningTest extends TestCase
{
    private function clean(?string $url): ?string
    {
        $method = new \ReflectionMethod(TourController::class, 'cleanTourUrl');
        $method->setAccessible(true);

        return $method->invoke(new TourController, $url);
    }

    public function test_strips_tracking_params_keeps_path(): void
    {
        $url = 'https://www.etstur.com/Yurtdisi-Tatil-Turlari/new-york-JTS26N5YOT?_gl=1*v1574q&gclid=ABC123&gclsrc=aw.ds&gbraid=XYZ';
        $this->assertSame(
            'https://www.etstur.com/Yurtdisi-Tatil-Turlari/new-york-JTS26N5YOT',
            $this->clean($url)
        );
    }

    public function test_keeps_meaningful_query_params(): void
    {
        $url = 'https://acenta.com/tur?id=42&utm_source=google&gclid=x';
        $this->assertSame('https://acenta.com/tur?id=42', $this->clean($url));
    }

    public function test_null_and_plain_url_unchanged(): void
    {
        $this->assertNull($this->clean(null));
        $this->assertNull($this->clean(''));
        $this->assertSame('https://acenta.com/tur', $this->clean('https://acenta.com/tur'));
    }

    private function normalizeItinerary($input)
    {
        $method = new \ReflectionMethod(TourController::class, 'normalizeItinerary');
        $method->setAccessible(true);

        return $method->invoke(new TourController, $input);
    }

    public function test_itinerary_drops_empty_days_and_keeps_filled(): void
    {
        $result = $this->normalizeItinerary([
            ['title' => '1. Gün', 'content' => 'Lima'],
            ['title' => '', 'content' => ''],          // boş → atılır
            ['title' => '2. Gün', 'content' => 'Cusco'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame('1. Gün', $result[0]['title']);
        $this->assertSame('Cusco', $result[1]['content']);
    }

    public function test_itinerary_null_when_empty_or_invalid(): void
    {
        $this->assertNull($this->normalizeItinerary(null));
        $this->assertNull($this->normalizeItinerary([]));
        $this->assertNull($this->normalizeItinerary([['title' => '', 'content' => '']]));
    }
}
