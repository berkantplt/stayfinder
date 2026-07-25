<?php

namespace Tests\Unit;

use App\Services\AiSearch\RecreationQuizService;
use PHPUnit\Framework\TestCase;

class RecreationQuizServiceTest extends TestCase
{
    private RecreationQuizService $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quiz = new RecreationQuizService();
    }

    /** Prototipte yakalanan tavan etkisi düzeltmesi: kaydırak 60 + zirve + s6
     *  yenilik cevabı artık 1.0'a çakılmamalı (payda 5). */
    public function test_yenilik_denominator_prevents_early_ceiling(): void
    {
        $result = $this->quiz->score([
            's1' => 60,
            's2' => ['zirve', 'koy', 'antik'],
            's3' => 60,
            's4' => 1, 's5' => 2, 's6' => 1, 's7' => 1, 's8a' => 1, 's9' => 0,
        ]);

        // 60/100*2 + zirve 1 + s6 2 = 4.2 puan / 5 = 0.84
        $this->assertSame(0.84, $result['vector']['yenilik']);
        $this->assertLessThan(1.0, $result['vector']['yenilik']);
    }

    public function test_axes_score_from_cards_chips_and_sliders(): void
    {
        $result = $this->quiz->score([
            's1' => 0,
            's2' => ['pazar', 'fest', 'rafting'],
            's3' => 80,
            's4' => 0,   // hemen denerim: gastro+2 yenilik+1
            's5' => 0,   // kalabalık müzik: sosyal+2
            's6' => 0,   // prestij+2
            's7' => 2,   // deneyimli
            's8a' => 0,  // yerel hayata karışmak: kultur+2 sosyal+1
            's9' => 2,   // yeni şeyler: yenilik+2
        ]);

        $v = $result['vector'];
        $this->assertSame(1.0, $v['gastro']);      // pazar 2 + s4 2 = 4/4
        $this->assertSame(1.0, $v['sosyal']);      // fest 2 + s5 2 + s8a 1 = 5 → tavan
        $this->assertSame(0.5, $v['macera']);      // rafting 2 = 2/4
        $this->assertSame(0.5, $v['prestij']);     // s6 2 = 2/4
        $this->assertSame(0.5, $v['kultur']);      // s8a 2 = 2/4
        $this->assertSame(0.6, $v['yenilik']);     // s4 1 + s9 2 = 3/5
        $this->assertSame(80, $result['tempo']);
        $this->assertSame('cok', $result['experience']);
        $this->assertContains('doga', $result['tags']);
    }

    public function test_persona_priority_breaks_ties(): void
    {
        // gastro ile kultur eşitse öncelik sırasındaki gastro kazanır
        $persona = $this->quiz->personaFor([
            'gastro' => 0.8, 'kultur' => 0.8, 'macera' => 0, 'sosyal' => 0,
            'prestij' => 0, 'yenilik' => 0, 'sukunet' => 0, 'kacis' => 0,
        ]);
        $this->assertSame('gastro', $persona['key']);
        $this->assertStringContainsString('Gurme', $persona['label']);
    }

    public function test_unknown_card_ids_and_extra_cards_are_ignored(): void
    {
        $result = $this->quiz->score([
            's1' => 50,
            's2' => ['hacker', 'koy', 'pazar', 'antik', 'fest'], // bilinmeyen + 3'ten fazla
            's3' => 50, 's4' => 2, 's5' => 2, 's6' => 2, 's7' => 0, 's8b' => 0, 's9' => 1,
        ]);

        // İlk 3 geçerli slottan yalnız koy+pazar sayılır (hacker yok sayılır ama slot yer)
        $this->assertSame(0.0, $result['vector']['sosyal']); // fest 4. eleman — kırpıldı
    }

    public function test_followup_query_built_from_top_axes_and_tags(): void
    {
        $query = $this->quiz->followupQuery(
            ['sukunet' => 0.9, 'yenilik' => 0.8, 'gastro' => 0.2, 'kultur' => 0, 'macera' => 0, 'sosyal' => 0, 'prestij' => 0, 'kacis' => 0],
            ['doga']
        );

        $this->assertStringContainsString('sakin', $query);
        $this->assertStringContainsString('az bilinen', $query);
        $this->assertStringContainsString('doğayla iç içe', $query);
    }

    public function test_definition_exposes_no_weights(): void
    {
        $def = $this->quiz->definition();

        $this->assertSame(RecreationQuizService::VERSION, $def['version']);
        $this->assertCount(10, $def['steps']);
        $this->assertStringNotContainsString('"ax"', json_encode($def, JSON_UNESCAPED_UNICODE));

        // Dallanma metadata'sı ön yüzün akışı kurabilmesi için mevcut olmalı
        $s8a = collect($def['steps'])->firstWhere('key', 's8a');
        $this->assertSame('s7', $s8a['branch_of']);
        $this->assertSame(['orta', 'cok'], $s8a['branch_when']);
    }
}
