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

    public function test_creating_top_level_category_via_general_form_has_no_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Genel formda üst kategori (parent_id boş) eklenince fiyat istenmez, 0 olur
        $this->actingAs($admin)
            ->post(route('admin.categories.store'), [
                'name' => 'Gruplama Kategorisi',
                'icon' => '📦',
                'sort_order' => 1,
                // parent_id ve monthly_price gönderilmiyor
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Gruplama Kategorisi',
            'parent_id' => null,
            'monthly_price' => '0.00',
        ]);
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
