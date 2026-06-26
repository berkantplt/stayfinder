<?php

namespace Tests\Feature;

use App\Models\FeaturedCity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedCityLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_featured_city_with_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.featured_cities.store'), [
                'name' => 'Paris',
                'country' => 'Fransa',
                'link' => '/turlar?destination=Paris',
                'sort_order' => 0,
            ])
            ->assertRedirect(route('admin.featured_cities.index'));

        $this->assertDatabaseHas('featured_cities', [
            'name' => 'Paris',
            'link' => '/turlar?destination=Paris',
        ]);
    }

    public function test_home_includes_city_link_in_story_data(): void
    {
        $city = FeaturedCity::create([
            'name' => 'Kapadokya',
            'country' => 'Türkiye',
            'link' => '/turlar?q=Kapadokya',
            'is_active' => true,
        ]);
        // Story listede görünmesi için en az 1 görsel gerekli
        $city->images()->create(['image_path' => 'featured_cities/test.jpg', 'sort_order' => 0]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/turlar?q=Kapadokya', false);
    }
}
