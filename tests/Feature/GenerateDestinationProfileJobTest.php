<?php

namespace Tests\Feature;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class GenerateDestinationProfileJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_writes_profile_from_llm_response(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'crowd' => 0.72,
                            'lively' => 0.55,
                            'reasoning' => 'Orta büyüklükte tarihi şehir, turist yoğunluğu mevsimsel.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        // Pre-create placeholder (DestinationProfileService normalde bunu yapar)
        DestinationProfile::create([
            'city' => 'Lizbon',
            'normalized_city' => 'lizbon',
            'crowd_score' => 0.50,
            'liveliness_score' => 0.50,
            'source' => DestinationProfile::SOURCE_DEFAULT,
        ]);

        (new GenerateDestinationProfileJob('Lizbon'))->handle();

        $profile = DestinationProfile::where('normalized_city', 'lizbon')->first();
        $this->assertNotNull($profile);
        $this->assertEqualsWithDelta(0.72, (float) $profile->crowd_score, 0.01);
        $this->assertEqualsWithDelta(0.55, (float) $profile->liveliness_score, 0.01);
        $this->assertSame(DestinationProfile::SOURCE_LLM, $profile->source);
        $this->assertNotNull($profile->generated_at);
        $this->assertStringContainsString('tarihi', $profile->reasoning);
    }

    public function test_job_clamps_out_of_range_values(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode(['crowd' => 1.5, 'lively' => -0.3]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateDestinationProfileJob('Edge'))->handle();

        $profile = DestinationProfile::where('normalized_city', 'edge')->firstOrFail();
        $this->assertEqualsWithDelta(1.0, (float) $profile->crowd_score, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $profile->liveliness_score, 0.01);
    }

    public function test_job_persists_enrichment_fields(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'crowd' => 0.85,
                            'lively' => 0.92,
                            'country' => 'Birleşik Arap Emirlikleri',
                            'summary' => 'Lüks alışveriş, çağdaş mimari ve sıcak çöl iklimiyle bilinen küresel bir metropol.',
                            'climate_by_month' => [
                                '1' => ['temp_c' => 24, 'condition' => 'ılık'],
                                '7' => ['temp_c' => 41, 'condition' => 'sıcak'],
                            ],
                            'vibe_tags' => ['luxury', 'shopping', 'beach', 'nightlife'],
                            'best_months' => [10, 11, 12, 1, 2, 3],
                            'crowded_months' => [12, 1, 2],
                            'requires_visa_for_tr' => false,
                            'reasoning' => 'Dubai, sıcak kuru çöl iklimi ve gelişmiş turizm altyapısıyla bilinir.',
                        ]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateDestinationProfileJob('Dubai'))->handle();

        $profile = DestinationProfile::where('normalized_city', 'dubai')->firstOrFail();

        $this->assertSame('Birleşik Arap Emirlikleri', $profile->country);
        $this->assertStringContainsString('lüks', mb_strtolower($profile->summary, 'UTF-8'));
        $this->assertSame([1, 2, 3, 10, 11, 12], $profile->best_months);
        $this->assertSame([1, 2, 12], $profile->crowded_months);
        $this->assertContains('luxury', $profile->vibe_tags);
        $this->assertFalse($profile->requires_visa_for_tr);
        $this->assertSame(DestinationProfile::CURRENT_ENRICHMENT_VERSION, $profile->enrichment_version);
        $this->assertNotNull($profile->climate_by_month);
    }

    public function test_job_filters_unknown_vibe_tags(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'crowd' => 0.5,
                            'lively' => 0.5,
                            'vibe_tags' => ['luxury', 'INVALID_TAG', 'beach', '   ', 'unknown_vibe'],
                        ]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateDestinationProfileJob('Test'))->handle();

        $profile = DestinationProfile::where('normalized_city', 'test')->firstOrFail();
        $this->assertSame(['luxury', 'beach'], $profile->vibe_tags);
    }

    public function test_job_filters_invalid_month_numbers(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'crowd' => 0.5,
                            'lively' => 0.5,
                            'best_months' => [3, 4, 13, 0, -1, 'foo', 11],
                        ]),
                    ],
                ]],
            ]),
        ]);

        (new GenerateDestinationProfileJob('MonthEdge'))->handle();

        $profile = DestinationProfile::where('normalized_city', 'monthedge')->firstOrFail();
        $this->assertSame([3, 4, 11], $profile->best_months);
    }

    public function test_job_throws_on_invalid_llm_response(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'not json',
                    ],
                ]],
            ]),
        ]);

        $this->expectException(\Throwable::class);
        (new GenerateDestinationProfileJob('Foo'))->handle();
    }
}
