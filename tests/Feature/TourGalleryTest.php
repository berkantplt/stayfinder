<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Services\TourImage\TourImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class TourGalleryTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Galeri Acenta',
            'slug' => 'galeri-acenta',
            'email' => 'galeri@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
        $this->category = Category::create(['name' => 'Tur', 'slug' => 'tur', 'is_active' => true]);
    }

    private function tourPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Galeri Turu',
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 2,
            'currency' => 'TRY',
            'pricing_options' => [
                ['price' => '5000', 'departure_dates' => [today()->addDays(15)->toDateString()]],
            ],
        ], $overrides);
    }

    public function test_upload_endpoint_stores_image_and_returns_path(): void
    {
        Storage::fake('public');

        $res = $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.image.upload'), [
                'image' => UploadedFile::fake()->image('foto.jpg', 800, 600),
            ]);

        $res->assertOk()->assertJson(['ok' => true]);
        $path = $res->json('path');
        $this->assertStringStartsWith('/storage/tours/', $path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $path));
    }

    public function test_store_saves_ordered_gallery_with_local_and_remote(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/local-1.jpg', 'localdata');
        // Gerçek görsel gövdesi: downloadAndStore artık boyut doğruluyor (min 300px)
        $im = imagecreatetruecolor(800, 500);
        ob_start();
        imagepng($im);
        imagedestroy($im);
        $remoteBody = (string) ob_get_clean();
        Http::fake([
            'https://1.1.1.1/*' => Http::response($remoteBody, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload([
                'gallery' => ['/storage/tours/local-1.jpg', 'https://1.1.1.1/photo-2.jpg'],
            ]))
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Galeri Turu');
        $this->assertCount(2, $tour->images);
        // Sıra korunur, ilk = kapak
        $this->assertSame('/storage/tours/local-1.jpg', $tour->images[0]);
        $this->assertSame($tour->images[0], $tour->image);
        // Uzak görsel indirilip /storage'a kaydedildi
        $this->assertStringStartsWith('/storage/tours/', $tour->images[1]);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $tour->images[1]));
    }

    public function test_image_service_rejects_private_remote_url(): void
    {
        Storage::fake('public');
        Http::fake();

        $svc = new TourImageService;
        $this->assertNull($svc->downloadAndStore('http://127.0.0.1/x.jpg'));
        $this->assertNull($svc->downloadAndStore('http://localhost/x.jpg'));
        Http::assertNothingSent();
    }

    public function test_image_service_rejects_non_image_content(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $svc = new TourImageService;
        $this->assertNull($svc->downloadAndStore('https://1.1.1.1/notimage'));
    }

    public function test_import_returns_ordered_tour_gallery_images(): void
    {
        $html = '<html><head>'
            .'<meta property="og:image" content="https://cdn.example.com/uploads/tours/5/orginal/photo-1.jpg">'
            .'</head><body>'
            .'<a href="https://cdn.example.com/uploads/tours/5/orginal/photo-2.jpg"></a>'
            .'<img data-src="https://cdn.example.com/uploads/tours/5/orginal/photo-3.png">'
            .'<img src="https://cdn.example.com/assets/logo.png">' // logo → elenir
            .str_repeat('dolgu ', 60)
            .'</body></html>';

        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode(['title' => 'Tur', 'departure_dates' => []])],
                ]],
            ]),
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/tur'])
            ->assertOk()
            ->json('data');

        $names = array_map(fn ($u) => basename(parse_url($u, PHP_URL_PATH)), $data['image_urls']);
        $this->assertContains('photo-1.jpg', $names);
        $this->assertContains('photo-2.jpg', $names);
        $this->assertContains('photo-3.png', $names);
        $this->assertNotContains('logo.png', $names); // logo elendi
    }
}
