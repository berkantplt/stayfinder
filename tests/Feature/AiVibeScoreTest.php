<?php

namespace Tests\Feature;

use App\Services\AiSearch\TourScorer;
use Tests\TestCase;

class AiVibeScoreTest extends TestCase
{
    private function score(?bool $wantsNature, ?bool $wantsLively, ?bool $avoidCrowded, ?array $vibeTags): float
    {
        return app(TourScorer::class)->scoreVibeMatch($wantsNature, $wantsLively, $avoidCrowded, $vibeTags);
    }

    public function test_wants_nature_with_nature_tag_returns_positive_bonus(): void
    {
        $withTag = $this->score(true, null, null, ['nature', 'cultural']);
        $withoutTag = $this->score(true, null, null, ['cultural', 'historical']);

        $this->assertGreaterThan($withoutTag, $withTag);
        $this->assertEqualsWithDelta(0.10, $withTag, 0.0001);
        $this->assertEqualsWithDelta(0.0, $withoutTag, 0.0001);
    }

    public function test_wants_lively_matches_nightlife_or_luxury(): void
    {
        $this->assertEqualsWithDelta(0.10, $this->score(null, true, null, ['nightlife']), 0.0001);
        $this->assertEqualsWithDelta(0.10, $this->score(null, true, null, ['luxury']), 0.0001);
        $this->assertEqualsWithDelta(0.10, $this->score(null, true, null, ['luxury', 'nightlife']), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->score(null, true, null, ['nature']), 0.0001);
    }

    public function test_avoid_crowded_penalizes_nightlife_or_shopping(): void
    {
        $this->assertEqualsWithDelta(-0.08, $this->score(null, null, true, ['nightlife']), 0.0001);
        $this->assertEqualsWithDelta(-0.08, $this->score(null, null, true, ['shopping']), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->score(null, null, true, ['nature', 'cultural']), 0.0001);
    }

    public function test_multiple_intents_compound(): void
    {
        // wants_nature + wants_lively + nature+luxury tags → +0.10 +0.10
        $score = $this->score(true, true, null, ['nature', 'luxury']);
        $this->assertEqualsWithDelta(0.20, $score, 0.0001);

        // wants_lively + avoid_crowded ile nightlife tag → +0.10 -0.08 = +0.02
        $score = $this->score(null, true, true, ['nightlife']);
        $this->assertEqualsWithDelta(0.02, $score, 0.0001);
    }

    public function test_no_intent_or_no_tags_returns_zero(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->score(null, null, null, ['nature', 'luxury']), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->score(true, true, true, null), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->score(true, true, true, []), 0.0001);
    }

    public function test_false_intent_does_not_trigger_bonus(): void
    {
        // wants_nature açıkça false → bonus uygulanmaz (sadece true durumunda)
        $this->assertEqualsWithDelta(0.0, $this->score(false, null, null, ['nature']), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->score(null, false, null, ['nightlife']), 0.0001);
    }

    public function test_score_is_higher_for_destination_with_matching_vibe_than_without(): void
    {
        // Spec'in özü: aynı niyetle, tag dolu profil tag boş profilden yüksek skor verir
        $matched = $this->score(true, null, null, ['nature']);
        $unmatched = $this->score(true, null, null, ['historical', 'shopping']);

        $this->assertGreaterThan($unmatched, $matched);
    }
}
