<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tur eşleştirme testinin ana sayfadaki görünürlüğü (config: ai.quiz_enabled).
 *
 * Askıya alma SİLME değil: kod, rubrik, /tatil-karakteri ucu ve testler yerinde
 * kalıyor — yalnız giriş noktası gizleniyor. Bu testler ikisini de bekçiliyor,
 * çünkü bayrağı açtığımız gün girişin geri geldiğini kanıtlaması lazım.
 */
class QuizVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $agency = Agency::create([
            'name' => 'Quiz Acenta',
            'slug' => 'quiz-acenta',
            'email' => 'quiz@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Vitrin Turu',
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(20),
            'is_active' => true,
        ]);
    }

    public function test_bayrak_kapaliyken_ana_sayfada_test_girisi_gorunmez(): void
    {
        config(['ai.quiz_enabled' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Hangi tur sana uygun')
            ->assertDontSee('Testi başlat');
    }

    /** Askıdayken 16,9 KB'lık quiz modülü de gönderilmemeli — her sayfada boşuna inmesin. */
    public function test_bayrak_kapaliyken_quiz_js_modulu_yuklenmez(): void
    {
        config(['ai.quiz_enabled' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('window.turxturQuiz = (function', false)
            ->assertDontSee('quiz-modal');
    }

    public function test_bayrak_acikken_test_girisi_geri_gelir(): void
    {
        config(['ai.quiz_enabled' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Hangi tur sana uygun')
            ->assertSee('Testi başlat');
    }

    public function test_askidayken_ana_sayfanin_kalani_bozulmaz(): void
    {
        config(['ai.quiz_enabled' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Vitrin Turu')
            ->assertSee('En Uygun Turlar');
    }

    /**
     * Uç BİLEREK açık: LLM'siz ve maliyetsiz. Kapatılsaydı bayrak açıldığında
     * modal sessizce boş dönerdi — sohbet v1'de yaşanan tuzağın aynısı.
     */
    public function test_tatil_karakteri_ucu_askidayken_de_calisir(): void
    {
        config(['ai.quiz_enabled' => false]);

        $this->get(route('recreation.quiz.definition'), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonStructure(['quiz_version', 'tercih_sorulari', 'onem_sorusu']);
    }
}
