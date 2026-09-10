<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** "Sana uygun" sıralaması: test çözülmüşse seçenek çıkar, rubrik puanıyla sıralar, kartta gerekçe. */
class TourSortUygunTest extends TestCase
{
    use RefreshDatabase;

    private Tour $uzak;

    private Tour $yakin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Uygun Acenta', 'slug' => 'uygun-acenta', 'email' => 'u@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $tur = fn (string $baslik, int $fiyat) => Tour::create([
            'agency_id' => $agency->id, 'title' => $baslik, 'destination' => 'Kapadokya', 'description' => 'x',
            'price' => $fiyat, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
        // Uzak tur daha ucuz: fiyat sıralamasında önde, uygunlukta arkada olmalı
        $this->uzak = $tur('Yoğun Tempo Turu', 4000);
        $this->yakin = $tur('Sakin Kültür Turu', 6000);

        $puan = fn (Tour $t, int $tempo, int $kultur) => TourRubricScore::create([
            'tour_id' => $t->id, 'rubric_version' => Rubric::VERSION, 'input_hash' => 'h'.$t->id,
            'scores' => ['tempo' => ['value' => $tempo], 'kultur' => ['value' => $kultur]],
            'review_status' => TourRubricScore::STATUS_AUTO, 'scored_at' => now(),
        ]);
        $puan($this->uzak, 5, 1);
        $puan($this->yakin, 2, 4);
    }

    private function profil(): array
    {
        return ['recreation_quiz_result' => [
            'profil' => ['degerler' => ['tempo' => 20, 'kultur' => 80], 'agirliklar' => ['tempo' => 1.0, 'kultur' => 1.0]],
        ]];
    }

    public function test_test_cozulmusse_secenek_cikar_ve_uygunluga_gore_siralar(): void
    {
        $r = $this->withSession($this->profil())->get(route('tours.index', ['sort' => 'uygun']))->assertOk();

        $r->assertSee('value="uygun"', false)
            ->assertSee('Tatil karakterine göre sıralandı')
            ->assertSeeInOrder(['Sakin Kültür Turu', 'Yoğun Tempo Turu'])
            ->assertSee('örtüşüyor');
    }

    public function test_test_cozulmemisse_secenek_yok_ve_fiyata_duser(): void
    {
        $r = $this->get(route('tours.index', ['sort' => 'uygun']))->assertOk();

        $r->assertDontSee('value="uygun"', false)
            ->assertDontSee('Tatil karakterine göre sıralandı')
            ->assertSeeInOrder(['Yoğun Tempo Turu', 'Sakin Kültür Turu']);
    }
}
