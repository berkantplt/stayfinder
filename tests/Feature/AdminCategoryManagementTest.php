<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_and_delete_category_with_route_model_binding(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        // Fiyat alt kategorilerde olur → düzenleme testi bir alt kategori üzerinden
        $parent = Category::create([
            'name' => 'Grup', 'slug' => 'grup', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Eski Kategori',
            'slug' => 'eski-kategori',
            'icon' => '🧭',
            'parent_id' => $parent->id,
            'monthly_price' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Yeni Kategori',
                'icon' => '🏛️',
                'parent_id' => $parent->id,
                'monthly_price' => 1500,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Yeni Kategori',
            'slug' => 'yeni-kategori',
            'monthly_price' => '1500.00',
            'sort_order' => 2,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $category->fresh()))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_store_parent_creates_top_level_category_with_null_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // parent_id gönderilse bile yok sayılır — daima üst kategori olur
        $other = Category::create([
            'name' => 'Var Olan',
            'slug' => 'var-olan',
            'monthly_price' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.categories.parents.store'), [
                'name' => 'Yurt Dışı Turlar',
                'icon' => '🌍',
                'sort_order' => 3,
                'parent_id' => $other->id, // bilinçli olarak yok sayılmalı
            ])
            ->assertRedirect(route('admin.categories.parents'));

        // Üst kategori daima parent_id=null ve FİYATSIZ (0) olur
        $this->assertDatabaseHas('categories', [
            'name' => 'Yurt Dışı Turlar',
            'slug' => 'yurt-disi-turlar',
            'parent_id' => null,
            'monthly_price' => '0.00',
            'sort_order' => 3,
        ]);
    }

    public function test_store_creates_child_category_bound_to_parent_with_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Yurt İçi', 'slug' => 'yurt-ici', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Kültür Turları',
                'icon' => '🏛️',
                'parent_id' => $parent->id,
                'monthly_price' => 2500,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Kültür Turları',
            'parent_id' => $parent->id,
            'monthly_price' => '2500.00',
        ]);
    }

    public function test_store_rejects_child_category_without_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Parentsız',
                'icon' => '📦',
                'monthly_price' => 2000,
                'sort_order' => 1,
                // parent_id yok → reddedilmeli
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Parentsız']);
    }

    public function test_store_rejects_non_top_level_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Üst', 'slug' => 'ust', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);
        $child = Category::create([
            'name' => 'Alt', 'slug' => 'alt', 'parent_id' => $parent->id, 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        // Bir alt kategoriyi üst olarak seçmek (3. seviye) reddedilmeli
        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Torun',
                'parent_id' => $child->id,
                'monthly_price' => 1000,
                'sort_order' => 1,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('categories', ['name' => 'Torun']);
    }

    public function test_alt_kategori_page_lists_only_children(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Ust Grup Abc', 'slug' => 'ust-grup-abc', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);
        Category::create([
            'name' => 'Alt Urun Def', 'slug' => 'alt-urun-def', 'parent_id' => $parent->id, 'monthly_price' => 1500, 'sort_order' => 1, 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Alt Kategori Yönetimi');

        // Tabloya yalnızca alt kategoriler gelir (hepsinin parent_id'si dolu)
        $listed = $response->viewData('categories');
        $this->assertTrue($listed->every(fn ($c) => $c->parent_id !== null));
        $this->assertTrue($listed->contains('name', 'Alt Urun Def'));
        $this->assertFalse($listed->contains('name', 'Ust Grup Abc'));
    }

    public function test_alt_kategori_listesi_once_ust_kategoriye_sonra_sira_numarasina_gore_dizilir(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Üst kategoriler: Yurt İçi (sıra 1), Yurt Dışı (sıra 2)
        $yurtIci = Category::create(['name' => 'Yurt İçi', 'slug' => 'yurt-ici', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true]);
        $yurtDisi = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'monthly_price' => 0, 'sort_order' => 2, 'is_active' => true]);

        // Karışık ekleme sırası; aynı sıra numaraları (1) ama farklı üst kategoriler
        Category::create(['name' => 'Amerika', 'slug' => 'amerika', 'parent_id' => $yurtDisi->id, 'monthly_price' => 2000, 'sort_order' => 1, 'is_active' => true]);
        Category::create(['name' => 'GAP', 'slug' => 'gap', 'parent_id' => $yurtIci->id, 'monthly_price' => 2000, 'sort_order' => 1, 'is_active' => true]);
        Category::create(['name' => 'Karadeniz', 'slug' => 'karadeniz', 'parent_id' => $yurtIci->id, 'monthly_price' => 2000, 'sort_order' => 2, 'is_active' => true]);

        $listed = $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->viewData('categories');

        // Beklenen sıra: Yurt İçi grubu (GAP sıra1, Karadeniz sıra2), sonra Yurt Dışı grubu (Amerika)
        $this->assertSame(
            ['GAP', 'Karadeniz', 'Amerika'],
            $listed->pluck('name')->all()
        );
    }

    public function test_next_sort_per_parent_is_last_child_plus_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $dolu = Category::create(['name' => 'Dolu Grup', 'slug' => 'dolu-grup', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true]);
        $bos = Category::create(['name' => 'Boş Grup', 'slug' => 'bos-grup', 'monthly_price' => 0, 'sort_order' => 2, 'is_active' => true]);

        Category::create(['name' => 'A1', 'slug' => 'a1', 'parent_id' => $dolu->id, 'monthly_price' => 2000, 'sort_order' => 3, 'is_active' => true]);
        Category::create(['name' => 'A2', 'slug' => 'a2', 'parent_id' => $dolu->id, 'monthly_price' => 2000, 'sort_order' => 7, 'is_active' => true]);

        $map = $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->viewData('nextSortByParent');

        // Dolu grupta en yüksek sıra 7 → sonraki 8; boş grup haritada yok (JS varsayılanı 1)
        $this->assertSame(8, $map[$dolu->id]);
        $this->assertArrayNotHasKey($bos->id, $map->all());
    }

    public function test_parents_page_lists_parent_categories_with_child_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Ana Grup', 'slug' => 'ana-grup', 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);
        Category::create([
            'name' => 'Alt Tur', 'slug' => 'alt-tur', 'parent_id' => $parent->id, 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.parents'))
            ->assertOk()
            ->assertSee('Üst Kategori Yönetimi')
            ->assertSee('Ana Grup')
            ->assertSee('1 alt kategori');
    }

    public function test_parents_page_does_not_list_child_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Ana Kategori', 'slug' => 'ana-kategori', 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);
        Category::create([
            'name' => 'Alt Kategori Xyz', 'slug' => 'alt-kategori-xyz', 'parent_id' => $parent->id, 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.parents'))
            ->assertOk()
            ->assertSee('Ana Kategori')
            ->assertDontSee('Alt Kategori Xyz');
    }
}
