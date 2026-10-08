<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin > Raporlar (2026-10 yeniden tasarım + mantık düzeltmeleri).
 *
 * Doğrulanan kurallar: manuel 0 TL siparişler tahsilata girmez ama tablolarda
 * "manuel" sütununda görünür; iptal edilen abonelik "dolan" sayılmaz; ücretli
 * uzatma "yenilenen"dir, "yeni" değil; yaşam boyu sayaç arşivdeki turları
 * saymaz; kuyruk önbellekten bağımsız okunur; ?yenile=1 önbelleği tazeler;
 * CSV'lerde manuel sütunu var; 7 gün favori oranı yalnız penceresi kapananları sayar.
 */
class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private int $sayac = 0;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function acenta(string $ad = 'Rapor Acentası'): Agency
    {
        $this->sayac++;

        return Agency::create([
            'name' => $ad,
            'slug' => 'rapor-acentasi-'.$this->sayac,
            'email' => 'rapor'.$this->sayac.'@example.com',
            'is_active' => true,
        ]);
    }

    private function kategori(string $ad = 'Kültür Turları', float $fiyat = 2000): Category
    {
        $this->sayac++;
        $ust = Category::create([
            'name' => 'Tur Grupları '.$this->sayac,
            'slug' => 'tur-gruplari-'.$this->sayac,
            'monthly_price' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Category::create([
            'name' => $ad,
            'slug' => 'kategori-'.$this->sayac,
            'parent_id' => $ust->id,
            'monthly_price' => $fiyat,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function odenmisSiparis(Agency $agency, Category $category, string $provider, float $tutar, bool $otomatik = false): AgencyCategoryOrder
    {
        $this->sayac++;
        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => 'KYM-RAPOR-'.$this->sayac,
            'billing_cycle' => 'monthly',
            'subtotal' => $tutar,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PAID,
            'payment_provider' => $provider,
            'auto_renewal' => $otomatik,
            'purchased_at' => now(),
            'paid_at' => now(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'item_type' => AgencyCategoryOrderItem::TYPE_LICENSE,
            'unit_price' => $tutar,
            'billing_cycle' => 'monthly',
        ]);

        return $order;
    }

    private function abonelik(Agency $agency, Category $category, array $ek = []): AgencyCategorySubscription
    {
        return AgencyCategorySubscription::create(array_merge([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => $category->monthly_price,
            'extra_tour_slots' => 0,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ], $ek));
    }

    private function tur(Agency $agency, Category $category, string $baslik, array $ek = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => $baslik,
            'destination' => 'Kapadokya',
            'description' => 'Rapor testi turu.',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(7),
            'return_date' => today()->addDays(9),
            'is_active' => true,
        ], $ek));
    }

    public function test_sayfa_admin_icin_acilir_ve_yeni_bilesenleri_basar(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Raporlar')
            ->assertSee('rp-seg', false)                 // dönem segmenti
            ->assertSee('name="donem"', false)
            ->assertSee('Dışa aktar (CSV)')
            ->assertSee('Manuel / bedava')               // ücretli / manuel ayrımı
            ->assertSee('yenilenen')
            ->assertSee('önbelleğe girmez')              // kuyruk canlı
            ->assertDontSee('class="table-wrap"', false) // 3. sütunu gizleyen sarmalayıcı yok (layout CSS'inde kural metni kalır)
            ->assertDontSee('class="table"', false);     // panel .table kuralı (td 20px 24px) tabloları ezmesin
    }

    public function test_admin_disindaki_rol_sayfayi_acamaz(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'visitor']))
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    public function test_manuel_siparisler_tahsilata_girmez_tablolarda_manuel_sutununda_gorunur(): void
    {
        $agency = $this->acenta();
        $category = $this->kategori();
        $this->odenmisSiparis($agency, $category, AgencyCategoryOrder::PROVIDER_MANUAL, 0);
        $this->odenmisSiparis($agency, $category, AgencyCategoryOrder::PROVIDER_IYZICO, 2000);

        $response = $this->actingAs($this->admin())->get(route('admin.reports.index'))->assertOk();
        $g = $response->viewData('rapor')['gelir'];

        $this->assertSame(1, $g['tahsilat']['adet'], 'Manuel sipariş ücretli tahsilata girmemeli');
        $this->assertSame(2000.0, $g['tahsilat']['tutar']);
        $this->assertSame(1, $g['manuelSiparis']);

        $kategori = $g['kategoriGelir']->first();
        $this->assertSame(2, (int) $kategori->kalem);
        $this->assertSame(1, (int) $kategori->manuel);
        $this->assertSame(2000.0, (float) $kategori->tutar);

        $acenta = $g['acentaGelir']->first();
        $this->assertSame(2, (int) $acenta->siparis);
        $this->assertSame(1, (int) $acenta->manuel);

        $response->assertSee('2.000 ₺');
    }

    public function test_iptal_edilen_abonelik_dolan_olarak_tekrar_sayilmaz(): void
    {
        $agency = $this->acenta();
        // Acenta iptal etti, dönem sonunda expire komutu kapattı → yalnız "iptal"
        $this->abonelik($agency, $this->kategori('A'), [
            'status' => AgencyCategorySubscription::STATUS_EXPIRED,
            'started_at' => today()->subMonths(3),
            'expires_at' => today(),
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);
        // İptal edilmeden süresi bitti → "dolan"
        $this->abonelik($agency, $this->kategori('B'), [
            'status' => AgencyCategorySubscription::STATUS_EXPIRED,
            'started_at' => today()->subMonths(3),
            'expires_at' => today(),
        ]);

        $hareket = $this->actingAs($this->admin())->get(route('admin.reports.index'))
            ->assertOk()->viewData('rapor')['gelir']['hareket'];

        $this->assertSame(1, $hareket['iptal']);
        $this->assertSame(1, $hareket['dolan'], 'İptal edilen abonelik dolan sayılmamalı');
        $this->assertSame(0, $hareket['yeni'], 'Üç ay önce başlayanlar yeni değil');
    }

    public function test_ucretli_uzatma_yenilenen_sayilir_yeni_sayilmaz_manuel_uzatma_sayilmaz(): void
    {
        // 1) Eski abonelik + bugün ücretli sipariş → yenilenen
        $a1 = $this->acenta('Yenileyen');
        $k1 = $this->kategori('Yenileme Kategorisi');
        $this->abonelik($a1, $k1, ['started_at' => today()->subMonths(2), 'expires_at' => today()->addMonth()]);
        $this->odenmisSiparis($a1, $k1, AgencyCategoryOrder::PROVIDER_IYZICO, 2000, true);

        // 2) Bugün başlayan abonelik + bugün ücretli sipariş → yeni
        $a2 = $this->acenta('Yeni Gelen');
        $k2 = $this->kategori('Yeni Kategori');
        $this->abonelik($a2, $k2, ['started_at' => today(), 'expires_at' => today()->addMonth()]);
        $this->odenmisSiparis($a2, $k2, AgencyCategoryOrder::PROVIDER_IYZICO, 2000);

        // 3) Eski abonelik + bugün MANUEL sipariş → ne yenilenen ne yeni
        $a3 = $this->acenta('Bedava Uzatılan');
        $k3 = $this->kategori('Manuel Kategori');
        $this->abonelik($a3, $k3, ['started_at' => today()->subMonth(), 'expires_at' => today()->addMonths(2)]);
        $this->odenmisSiparis($a3, $k3, AgencyCategoryOrder::PROVIDER_MANUAL, 0);

        $g = $this->actingAs($this->admin())->get(route('admin.reports.index'))
            ->assertOk()->viewData('rapor')['gelir'];

        $this->assertSame(1, $g['hareket']['yenilenen']);
        $this->assertSame(1, $g['hareket']['yeni']);
        $this->assertSame(0, $g['hareket']['yeniManuel']);
        $this->assertSame(1, $g['otomatikYenileme']);
        $this->assertSame(2, $g['tahsilat']['adet']);
    }

    public function test_yasam_boyu_sayac_arsivdeki_turlari_haric_tutar(): void
    {
        $agency = $this->acenta();
        $category = $this->kategori();
        $this->tur($agency, $category, 'Aktif Tur', ['views_count' => 10, 'clicks_count' => 4]);
        $this->tur($agency, $category, 'Arşiv Tur', ['views_count' => 7, 'clicks_count' => 3])->delete();

        $t = $this->actingAs($this->admin())->get(route('admin.reports.index'))
            ->assertOk()->viewData('rapor')['trafik'];

        $this->assertSame(10, $t['yasamBoyu']['views'], 'Dashboard ile aynı kural: arşivdeki tur sayılmaz');
        $this->assertSame(4, $t['yasamBoyu']['clicks']);
    }

    public function test_kuyruk_onbellekten_bagimsiz_canli_okunur(): void
    {
        $admin = $this->admin();

        $ilk = $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
        $this->assertSame(0, $ilk->viewData('kuyruk')['bekleyen']);
        $hesaplandi = $ilk->viewData('rapor')['hesaplandi'];

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'available_at' => time() - 120,
            'created_at' => time() - 120,
        ]);

        $ikinci = $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
        $this->assertSame($hesaplandi, $ikinci->viewData('rapor')['hesaplandi'], 'Rapor hâlâ önbellekten gelmeli');
        $this->assertSame(1, $ikinci->viewData('kuyruk')['bekleyen'], 'Kuyruk önbelleğe takılmamalı');
        $this->assertSame(2, $ikinci->viewData('kuyruk')['enEskiDakika']);
    }

    public function test_yenile_parametresi_onbellegi_tazeler(): void
    {
        $admin = $this->admin();
        $agency = $this->acenta();
        $category = $this->kategori();

        $this->assertSame(0, $this->actingAs($admin)->get(route('admin.reports.index'))->viewData('rapor')['gelir']['tahsilat']['adet']);

        $this->odenmisSiparis($agency, $category, AgencyCategoryOrder::PROVIDER_IYZICO, 2000);

        $this->assertSame(0, $this->actingAs($admin)->get(route('admin.reports.index'))->viewData('rapor')['gelir']['tahsilat']['adet'], '5 dk önbellek: eski değer');
        $this->assertSame(1, $this->actingAs($admin)->get(route('admin.reports.index', ['yenile' => 1]))->viewData('rapor')['gelir']['tahsilat']['adet'], '?yenile=1 tazeler');
    }

    public function test_csv_kategori_ve_acenta_tablolari_manuel_sutunu_tasir(): void
    {
        $agency = $this->acenta('Csv Acentası');
        $category = $this->kategori('Csv Kategorisi');
        $this->odenmisSiparis($agency, $category, AgencyCategoryOrder::PROVIDER_MANUAL, 0);
        $this->odenmisSiparis($agency, $category, AgencyCategoryOrder::PROVIDER_IYZICO, 2000);

        $kategori = $this->actingAs($this->admin())->get(route('admin.reports.export', ['tablo' => 'kategori-gelir']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Kategori;Kalem;"Manuel (0 TL)";"Tutar (TL)"', $kategori);
        $this->assertStringContainsString('"Csv Kategorisi";2;1;2000,00', $kategori); // virgül ayraç değil → fputcsv tırnaklamaz

        $acenta = $this->actingAs($this->admin())->get(route('admin.reports.export', ['tablo' => 'acenta-gelir']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('Acenta;"Ödenmiş Sipariş";"Manuel (0 TL)";"Tutar (TL)"', $acenta);
        $this->assertStringContainsString('"Csv Acentası";2;1;2000,00', $acenta);
    }

    public function test_yedi_gun_favori_orani_penceresi_kapanmayan_kayitlari_saymaz(): void
    {
        $agency = $this->acenta();
        $category = $this->kategori();
        $tour = $this->tur($agency, $category, 'Favori Tur');

        // Penceresi KAPANDI (10 gün önce kayıt), 3. gün favori ekledi → sayılır
        $eski = User::factory()->create(['role' => 'visitor', 'created_at' => now()->subDays(10)]);
        DB::table('favorites')->insert(['user_id' => $eski->id, 'tour_id' => $tour->id, 'created_at' => now()->subDays(7), 'updated_at' => now()->subDays(7)]);

        // Penceresi AÇIK (2 gün önce kayıt), favori ekledi → oranı etkilemez
        $yeni = User::factory()->create(['role' => 'visitor', 'created_at' => now()->subDays(2)]);
        DB::table('favorites')->insert(['user_id' => $yeni->id, 'tour_id' => $tour->id, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        $k = $this->actingAs($this->admin())->get(route('admin.reports.index', ['donem' => 'son-90']))
            ->assertOk()->viewData('rapor')['urun']['kullanici'];

        $this->assertSame(2, $k['kayit']);
        $this->assertSame(1, $k['yediGunUygun']);
        $this->assertSame(1, $k['yediGunFavori']);
        $this->assertSame(100.0, $k['yediGunOran']);
    }
}
