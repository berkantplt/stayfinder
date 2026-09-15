<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C9 — Acenta tur listesindeki "Yayın" sütunu: "Aktif" ayarı tek başına sitede
 * göründüğü anlamına gelmez. Abonelik yoksa/bitmişse, kategori pasifse veya
 * acenta pasifse sebep rozet altında yazılır; hesap tur başına sorgu atmaz.
 */
class AgencyTourListPublishStatusTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Yayın Acenta',
            'slug' => 'yayin-acenta',
            'email' => 'yayin@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => false,
        ]);

        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $this->agency->id]);

        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $this->category = Category::create([
            'name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id, 'monthly_price' => 1000,
        ]);
    }

    private function makeTour(string $title, bool $active = true): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(),
            'destination' => 'Fransa',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'price' => 30000,
            'currency' => 'TRY',
            'is_active' => $active,
            'requires_visa' => true,
        ]);
    }

    private function subscribe(): void
    {
        AgencyCategorySubscription::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'monthly_price' => 1000,
            'started_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
    }

    public function test_abonelik_yoksa_aktif_tur_yayinda_degil_olarak_gosterilir(): void
    {
        $this->makeTour('Paris Turu');

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertSee('Yayında değil')
            ->assertSee('Kategori yetkisi yok veya süresi dolmuş')
            ->assertDontSee('>Yayında<', false);
    }

    public function test_abonelik_varsa_yayinda_gosterilir(): void
    {
        $this->subscribe();
        $this->makeTour('Paris Turu');

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertSee('>Yayında<', false)
            ->assertDontSee('Yayında değil');
    }

    public function test_pasif_tur_sebebiyle_gosterilir(): void
    {
        $this->subscribe();
        $this->makeTour('Paris Turu', active: false);

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertSee('Pasif (sizin ayarınız)');
    }

    public function test_publicVisibilityIssue_isPubliclyVisible_ile_paritede(): void
    {
        $this->subscribe();
        $tour = $this->makeTour('Paris Turu');
        $this->assertNull($tour->publicVisibilityIssue());
        $this->assertTrue($tour->isPubliclyVisible());

        $this->category->update(['is_active' => false]);
        $tour = $tour->fresh();
        $this->assertSame('Kategori pasif', $tour->publicVisibilityIssue());
        $this->assertFalse($tour->isPubliclyVisible());

        $this->category->update(['is_active' => true]);
        $this->agency->update(['is_active' => false]);
        $tour = $tour->fresh();
        $this->assertSame('Acenta hesabı pasif', $tour->publicVisibilityIssue());
        $this->assertFalse($tour->isPubliclyVisible());
    }

    public function test_liste_tur_basina_ek_sorgu_atmaz(): void
    {
        $this->subscribe();
        $this->makeTour('Tur 1');

        // Isınma: şema kontrolü, rozet cache'i gibi tek seferlik sorgular ölçüme girmesin
        $this->actingAs($this->agencyUser)->get(route('agency.tours.index'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.tours.index'))->assertOk();
        $birTur = count(DB::getQueryLog());

        foreach (range(2, 6) as $i) {
            $this->makeTour('Tur '.$i);
        }

        DB::flushQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.tours.index'))->assertOk();
        $altiTur = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($birTur, $altiTur, 'Yayın durumu hesabı tur sayısıyla büyüyen sorgu atmamalı');
    }
}
