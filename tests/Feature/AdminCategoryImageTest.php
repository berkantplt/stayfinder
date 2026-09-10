<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Üst kategori kart görseli: yükleme, değiştirme, kaldırma; ana sayfa kartı. */
class AdminCategoryImageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_ust_kategori_gorselle_olusturulur_ve_ana_sayfa_kartinda_gorunur(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())->post(route('admin.categories.parents.store'), [
            'name' => 'Kültür Turları',
            'icon' => '🏛️',
            'sort_order' => 1,
            'image_file' => UploadedFile::fake()->image('kultur.jpg', 800, 600),
        ])->assertRedirect(route('admin.categories.parents'));

        $kategori = Category::where('slug', 'kultur-turlari')->firstOrFail();
        $this->assertNotNull($kategori->image);
        Storage::disk('public')->assertExists($kategori->image);

        Category::create(['name' => 'Deniz Tatili', 'slug' => 'deniz-tatili', 'is_active' => true, 'sort_order' => 2]);
        $this->get(route('home'))->assertOk()
            ->assertSee('class="home-cat"', false)
            ->assertSee($kategori->image_url, false);
    }

    public function test_gorsel_kaldirilinca_dosya_silinir(): void
    {
        Storage::fake('public');
        $yol = UploadedFile::fake()->image('eski.jpg')->store('categories', 'public');
        $kategori = Category::create(['name' => 'Doğa', 'slug' => 'doga', 'is_active' => true, 'image' => $yol]);

        $this->actingAs($this->admin())->put(route('admin.categories.update', $kategori), [
            'name' => 'Doğa', 'sort_order' => 0, 'remove_image' => 1,
        ])->assertRedirect(route('admin.categories.parents'));

        $this->assertNull($kategori->fresh()->image);
        Storage::disk('public')->assertMissing($yol);
    }
}
