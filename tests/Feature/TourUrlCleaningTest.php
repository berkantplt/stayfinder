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
}
