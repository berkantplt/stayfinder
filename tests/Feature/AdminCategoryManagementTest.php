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

        $category = Category::create([
            'name' => 'Eski Kategori',
            'slug' => 'eski-kategori',
            'icon' => '🧭',
            'monthly_price' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.categories.update', $category), [
                'name' => 'Yeni Kategori',
                'icon' => '🏛️',
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
                'monthly_price' => 2500,
                'sort_order' => 3,
                'parent_id' => $other->id, // bilinçli olarak yok sayılmalı
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Yurt Dışı Turlar',
            'slug' => 'yurt-disi-turlar',
            'parent_id' => null,
            'monthly_price' => '2500.00',
            'sort_order' => 3,
        ]);
    }

    public function test_parent_management_section_lists_parent_categories_with_child_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $parent = Category::create([
            'name' => 'Ana Grup', 'slug' => 'ana-grup', 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);
        Category::create([
            'name' => 'Alt Tur', 'slug' => 'alt-tur', 'parent_id' => $parent->id, 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee('Üst Kategori Yönetimi')
            ->assertSee('Ana Grup')
            ->assertSee('1 alt kategori');
    }
}
