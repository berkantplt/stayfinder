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
}
