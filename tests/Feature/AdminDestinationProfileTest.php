<?php

namespace Tests\Feature;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminDestinationProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_non_admin_cannot_access_index(): void
    {
        $visitor = User::factory()->create(['role' => 'visitor']);

        $this->actingAs($visitor)
            ->get(route('admin.destination-profiles.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get(route('admin.destination-profiles.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_sees_index_with_profiles(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedProfile('Roma');
        $this->seedProfile('Paris');

        $this->actingAs($admin)
            ->get(route('admin.destination-profiles.index'))
            ->assertOk()
            ->assertSee('Roma')
            ->assertSee('Paris');
    }

    public function test_filter_by_source_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedProfile('LlmCity', ['source' => DestinationProfile::SOURCE_LLM]);
        $this->seedProfile('ManualCity', ['source' => DestinationProfile::SOURCE_MANUAL]);

        $this->actingAs($admin)
            ->get(route('admin.destination-profiles.index', ['source' => 'manual']))
            ->assertOk()
            ->assertSee('ManualCity')
            ->assertDontSee('LlmCity');
    }

    public function test_admin_can_update_profile_and_source_becomes_manual(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('Roma', [
            'source' => DestinationProfile::SOURCE_LLM,
            'enrichment_version' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.destination-profiles.update', $profile), [
                'city' => 'Roma',
                'country' => 'İtalya',
                'summary' => 'Antik tarih + sanat',
                'crowd_score' => 0.85,
                'liveliness_score' => 0.78,
                'vibe_tags' => 'cultural, historical',
                'best_months' => '4, 5, 9, 10',
                'crowded_months' => '7, 8',
                'requires_visa_for_tr' => '1',
                'climate_by_month' => '{"7":{"temp_c":30,"condition":"sıcak"}}',
            ])
            ->assertRedirect(route('admin.destination-profiles.edit', $profile));

        $profile->refresh();
        $this->assertSame('İtalya', $profile->country);
        $this->assertSame(['cultural', 'historical'], $profile->vibe_tags);
        $this->assertSame([4, 5, 9, 10], $profile->best_months);
        $this->assertTrue($profile->requires_visa_for_tr);
        $this->assertSame(DestinationProfile::SOURCE_MANUAL, $profile->source);
        $this->assertSame(DestinationProfile::CURRENT_ENRICHMENT_VERSION, $profile->enrichment_version);
    }

    public function test_invalid_vibe_tags_are_silently_filtered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('TagTest');

        $this->actingAs($admin)
            ->put(route('admin.destination-profiles.update', $profile), [
                'city' => 'TagTest',
                'crowd_score' => 0.5,
                'liveliness_score' => 0.5,
                'vibe_tags' => 'cultural, INVALID, beach, made_up_tag',
            ])
            ->assertRedirect();

        $this->assertSame(['cultural', 'beach'], $profile->fresh()->vibe_tags);
    }

    public function test_invalid_climate_json_returns_error(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('JsonTest');

        $this->actingAs($admin)
            ->put(route('admin.destination-profiles.update', $profile), [
                'city' => 'JsonTest',
                'crowd_score' => 0.5,
                'liveliness_score' => 0.5,
                'climate_by_month' => 'not-json-at-all',
            ])
            ->assertSessionHasErrors('climate_by_month');
    }

    public function test_invalid_months_outside_range_are_filtered(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('MonthTest');

        $this->actingAs($admin)
            ->put(route('admin.destination-profiles.update', $profile), [
                'city' => 'MonthTest',
                'crowd_score' => 0.5,
                'liveliness_score' => 0.5,
                'best_months' => '0, 5, 13, 11, foo',
            ])
            ->assertRedirect();

        $this->assertSame([5, 11], $profile->fresh()->best_months);
    }

    public function test_regenerate_dispatches_enrichment_job(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('Reykjavik');

        $this->actingAs($admin)
            ->post(route('admin.destination-profiles.regenerate', $profile))
            ->assertRedirect(route('admin.destination-profiles.edit', $profile));

        Queue::assertPushed(GenerateDestinationProfileJob::class, function ($job) use ($profile) {
            return $job->city === $profile->city;
        });
    }

    public function test_non_admin_cannot_regenerate(): void
    {
        $visitor = User::factory()->create(['role' => 'visitor']);
        $profile = $this->seedProfile('ProtectedCity');

        $this->actingAs($visitor)
            ->post(route('admin.destination-profiles.regenerate', $profile))
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_destroy_default_source_succeeds(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('DefaultCity', ['source' => DestinationProfile::SOURCE_DEFAULT]);

        $this->actingAs($admin)
            ->delete(route('admin.destination-profiles.destroy', $profile))
            ->assertRedirect(route('admin.destination-profiles.index'));

        $this->assertDatabaseMissing('destination_profiles', ['id' => $profile->id]);
    }

    public function test_destroy_manual_requires_confirm(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = $this->seedProfile('ManualCity', ['source' => DestinationProfile::SOURCE_MANUAL]);

        $this->actingAs($admin)
            ->delete(route('admin.destination-profiles.destroy', $profile))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('destination_profiles', ['id' => $profile->id]);

        // confirm=1 ile silinir
        $this->actingAs($admin)
            ->delete(route('admin.destination-profiles.destroy', $profile), ['confirm' => 1])
            ->assertRedirect();

        $this->assertDatabaseMissing('destination_profiles', ['id' => $profile->id]);
    }

    private function seedProfile(string $city, array $overrides = []): DestinationProfile
    {
        return DestinationProfile::create(array_merge([
            'city' => $city,
            'normalized_city' => DestinationProfile::normalize($city),
            'crowd_score' => 0.50,
            'liveliness_score' => 0.50,
            'source' => DestinationProfile::SOURCE_LLM,
            'enrichment_version' => DestinationProfile::CURRENT_ENRICHMENT_VERSION,
        ], $overrides));
    }
}
