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
