<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\CategoryBanner;
use App\Models\Tour;
use App\Models\User;
use App\Support\CategoryHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kategori landing hero'su: admin'in kategori banner'ı (Kategori Banner
 * Yönetimi) ve sayfadaki çözümleme sırası (kendi → üst → genel → tur görseli).
 *
 * Ana sayfa karuselinin Banner modeliyle ilgisi yok; o BannerWhiteVeilTest'te.
 */
class CategoryBannerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function acenta(string $ad = 'Acenta'): Agency
    {
        return Agency::create([
            'name' => $ad.' '.uniqid(),
            'slug' => 'acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function tur(Category $kategori, array $attributes = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->acenta()->id,
            'category_id' => $kategori->id,
            'title' => 'Büyük Balkan Turu',
            'destination' => 'Saraybosna',
            'description' => 'Balkan turu.',
            'price' => 12000,
            'currency' => 'TRY',
            'duration_days' => 8,
            'duration_nights' => 7,
            'departure_date' => today()->addDays(30),
            'image' => 'https://cdn.ornek.com/balkan.jpg',
            'is_active' => true,
        ], $attributes));
    }

    // ── Admin sayfası ────────────────────────────────────────────────────────

    public function test_admin_sayfasi_ust_ve_alt_kategorileri_listeler(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id]);

        $this->actingAs($this->admin())->get(route('admin.category-banners.index'))
            ->assertOk()
            ->assertSee('Kategori Banner Yönetimi')
            ->assertSee('Genel varsayılan banner')
            ->assertSee('Yurt Dışı Turlar')
            ->assertSee('Balkan Turları')
            ->assertSee('/balkan-turlari');
    }

    public function test_admin_olmayan_kullanici_erisemez(): void
    {
        // Önce misafir (actingAs oturumu test boyunca kalır, sırası önemli)
        $this->get(route('admin.category-banners.index'))->assertRedirect();

        $ziyaretci = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($ziyaretci)->get(route('admin.category-banners.index'))->assertForbidden();
    }

    // ── Ekleme / çözümleme ───────────────────────────────────────────────────

    public function test_banner_eklenir_ve_kategori_sayfasinda_cikar(): void
    {
        Storage::fake('public');
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $this->tur($kategori);

        $this->actingAs($this->admin())->post(route('admin.category-banners.store'), [
            'category_id' => $kategori->id,
            'image' => UploadedFile::fake()->image('balkan.jpg', 1600, 600),
            'eyebrow' => 'Balkanları Keşfet',
            'title' => 'Bir yolculuk, birçok hikâye.',
            'subtitle' => 'Balkan turlarını keşfet, sana uygun rotayı bul.',
            'caption' => 'Mostar, Bosna-Hersek',
            'darkness' => 45,
        ])->assertRedirect(route('admin.category-banners.index'));

        $banner = CategoryBanner::where('category_id', $kategori->id)->firstOrFail();
        Storage::disk('public')->assertExists($banner->image);
        $this->assertSame(45, $banner->darkness);

        $this->get('/balkan-turlari')
            ->assertOk()
            ->assertSee($banner->image_url, false)
            ->assertSee('Balkanları Keşfet')
            ->assertSee('Bir yolculuk, birçok hikâye.')
            ->assertSee('Mostar, Bosna-Hersek')
            ->assertSee(CategoryHero::overlayCss(45), false);
    }

    public function test_alt_kategori_ust_kategorinin_bannerini_miras_alir(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        $alt = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id]);
        $this->tur($alt);
        CategoryBanner::create(['category_id' => $ust->id, 'image' => 'category-banners/ust.jpg', 'title' => 'Üstten gelen başlık']);

        $this->get('/balkan-turlari')
            ->assertOk()
            ->assertSee('category-banners/ust.jpg', false)
            ->assertSee('Üstten gelen başlık');
    }

    public function test_kendi_banneri_ust_kategorininkini_ezer(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        $alt = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id]);
        $this->tur($alt);
        CategoryBanner::create(['category_id' => $ust->id, 'image' => 'category-banners/ust.jpg', 'title' => 'Üstten gelen']);
        CategoryBanner::create(['category_id' => $alt->id, 'image' => 'category-banners/alt.jpg', 'title' => 'Kendi başlığı']);

        $this->get('/balkan-turlari')
            ->assertOk()
            ->assertSee('category-banners/alt.jpg', false)
            ->assertSee('Kendi başlığı')
            ->assertDontSee('Üstten gelen');
    }

    public function test_genel_varsayilan_bannersiz_kategoride_cikar(): void
    {
        $kategori = Category::create(['name' => 'Kayak Turları', 'slug' => 'kayak-turlari']);
        $this->tur($kategori);
        CategoryBanner::create(['category_id' => null, 'image' => 'category-banners/genel.jpg', 'eyebrow' => 'turXtur ile keşfet']);

        $this->get('/kayak-turlari')
            ->assertOk()
            ->assertSee('category-banners/genel.jpg', false)
            ->assertSee('turXtur ile keşfet');
    }

    public function test_bannersiz_kategori_ilk_tur_gorselini_ve_varsayilan_metinleri_kullanir(): void
    {
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $this->tur($kategori, ['image' => 'https://cdn.ornek.com/pahali.jpg', 'price' => 20000]);
        $this->tur($kategori, ['image' => 'https://cdn.ornek.com/ucuz.jpg', 'price' => 9000]);

        $vars = CategoryHero::defaults('Balkan Turları');

        $this->get('/balkan-turlari')
            ->assertOk()
            // en ucuz turun görseli (liste fiyata göre sıralı)
            ->assertSee('https://cdn.ornek.com/ucuz.jpg', false)
            ->assertSee($vars['eyebrow'])
            ->assertSee($vars['title'])
            ->assertSee('Balkan turlarını keşfet, sana uygun rotayı bul.');
    }

    public function test_yayindan_kaldirilan_banner_sayfada_cikmaz(): void
    {
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $this->tur($kategori);
        $banner = CategoryBanner::create(['category_id' => $kategori->id, 'image' => 'category-banners/x.jpg', 'title' => 'Pasif başlık']);

        $this->actingAs($this->admin())->patch(route('admin.category-banners.toggle', $banner))
            ->assertRedirect(route('admin.category-banners.index'));

        $this->assertFalse($banner->fresh()->is_active);
        $this->get('/balkan-turlari')->assertOk()->assertDontSee('Pasif başlık');
    }

    // ── Doğrulama ────────────────────────────────────────────────────────────

    public function test_ayni_kategoriye_ikinci_banner_eklenemez(): void
    {
        Storage::fake('public');
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        CategoryBanner::create(['category_id' => $kategori->id, 'image' => 'category-banners/x.jpg']);

        $this->actingAs($this->admin())->post(route('admin.category-banners.store'), [
            'category_id' => $kategori->id,
            'image' => UploadedFile::fake()->image('y.jpg'),
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(1, CategoryBanner::count());
    }

    public function test_ikinci_genel_varsayilan_eklenemez(): void
    {
        Storage::fake('public');
        CategoryBanner::create(['category_id' => null, 'image' => 'category-banners/genel.jpg']);

        $this->actingAs($this->admin())->post(route('admin.category-banners.store'), [
            'category_id' => '',
            'image' => UploadedFile::fake()->image('y.jpg'),
        ])->assertSessionHasErrors('category_id');

        $this->assertSame(1, CategoryBanner::count());
    }

    public function test_gorsel_olmadan_eklenemez(): void
    {
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);

        $this->actingAs($this->admin())->post(route('admin.category-banners.store'), [
            'category_id' => $kategori->id,
            'title' => 'Görselsiz',
        ])->assertSessionHasErrors('image');
    }

    // ── Güncelleme / silme ───────────────────────────────────────────────────

    public function test_guncellemede_yeni_gorsel_eskisini_siler(): void
    {
        Storage::fake('public');
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $eski = UploadedFile::fake()->image('eski.jpg')->store('category-banners', 'public');
        $banner = CategoryBanner::create(['category_id' => $kategori->id, 'image' => $eski, 'darkness' => 38]);

        $this->actingAs($this->admin())->put(route('admin.category-banners.update', $banner), [
            'image' => UploadedFile::fake()->image('yeni.jpg'),
            'title' => 'Yeni başlık',
            'darkness' => 60,
        ])->assertRedirect(route('admin.category-banners.index'));

        $banner->refresh();
        $this->assertSame('Yeni başlık', $banner->title);
        $this->assertSame(60, $banner->darkness);
        $this->assertNotSame($eski, $banner->image);
        Storage::disk('public')->assertMissing($eski);
        Storage::disk('public')->assertExists($banner->image);
    }

    public function test_metin_guncellemesi_gorseli_korur(): void
    {
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $banner = CategoryBanner::create(['category_id' => $kategori->id, 'image' => 'category-banners/kalici.jpg']);

        $this->actingAs($this->admin())->put(route('admin.category-banners.update', $banner), [
            'caption' => 'Mostar',
        ])->assertRedirect();

        $this->assertSame('category-banners/kalici.jpg', $banner->fresh()->image);
        $this->assertSame('Mostar', $banner->fresh()->caption);
    }

    public function test_silinince_dosya_da_silinir(): void
    {
        Storage::fake('public');
        $kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $yol = UploadedFile::fake()->image('x.jpg')->store('category-banners', 'public');
        $banner = CategoryBanner::create(['category_id' => $kategori->id, 'image' => $yol]);

        $this->actingAs($this->admin())->delete(route('admin.category-banners.destroy', $banner))
            ->assertRedirect(route('admin.category-banners.index'));

        $this->assertDatabaseMissing('category_banners', ['id' => $banner->id]);
        Storage::disk('public')->assertMissing($yol);
    }

    public function test_kategori_silinince_banneri_de_gider(): void
    {
        $kategori = Category::create(['name' => 'Geçici', 'slug' => 'gecici']);
        CategoryBanner::create(['category_id' => $kategori->id, 'image' => 'category-banners/x.jpg']);

        $kategori->delete();

        $this->assertSame(0, CategoryBanner::count());
    }
}
