<?php

namespace Tests\Feature;

use App\Jobs\ScoreTourRubricJob;
use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Models\User;
use App\Services\Matching\Rubric;
use App\Services\Matching\RubricCoverage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin "Tur Puanlama" (2026-10-08): puansız / bayat / incelemede kapsamı,
 * seçerek ya da sekme bütünüyle kuyruğa alma, admin tur listesindeki Puan
 * sütunu ve komutun sayfayla aynı sayıları basması.
 *
 * Canlı şikayet: sohbet 14 Kapadokya turundan 1'ini gösterdi — kalan 13'ün
 * rubrik puanı yoktu ve bunu gösteren ekran yoktu.
 */
class AdminRubricCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Test Tur', 'slug' => 'test-tur-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function tur(string $title, array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id, 'title' => $title, 'destination' => 'Kapadokya',
            'description' => 'd', 'price' => 9000, 'currency' => 'TRY', 'duration_days' => 2,
            'departure_date' => today()->addDays(30), 'return_date' => today()->addDays(31),
            'is_active' => true,
        ], $attrs));
    }

    private function puanla(Tour $tour, ?string $hash = null, string $status = TourRubricScore::STATUS_AUTO, array $nullBoyutlar = []): TourRubricScore
    {
        $payload = [];
        foreach (Rubric::dimensions() as $d) {
            $payload[$d] = in_array($d, $nullBoyutlar, true)
                ? ['value' => null, 'confidence' => 'low', 'evidence' => null]
                : ['value' => 3, 'confidence' => 'high', 'evidence' => 'test'];
        }

        return TourRubricScore::create([
            'tour_id' => $tour->id, 'rubric_version' => Rubric::VERSION,
            'input_hash' => $hash ?? RubricCoverage::girdiHash($tour->fresh()), 'scores' => $payload,
            'review_status' => $status, 'scored_at' => now(),
        ]);
    }

    /**
     * Gözlemci her Tour::create'te job'ı (fake) kuyruğa atar ve 10 dk'lık kilidi
     * yazar. Sayfanın kendi davranışını ölçmek için ikisi de sıfırlanır.
     */
    private function kuyruguSifirla(): void
    {
        Cache::flush();
        Queue::fake([ScoreTourRubricJob::class]);
    }

    /** Laravel database kuyruğunun yazdığı payload biçimi (uuid + displayName + serialize edilmiş komut). */
    private function payload(int $tourId): string
    {
        return json_encode([
            'uuid' => (string) Str::uuid(),
            'displayName' => ScoreTourRubricJob::class,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => ScoreTourRubricJob::class,
                'command' => serialize(new ScoreTourRubricJob($tourId)),
            ],
        ], JSON_THROW_ON_ERROR);
    }

    public function test_page_lists_unscored_active_tours_and_hides_inactive_and_scored_ones(): void
    {
        $puansiz = $this->tur('Kapadokya Balon Turu');
        $pasif = $this->tur('Pasif Kapadokya', ['is_active' => false]);
        $puanli = $this->tur('Kapadokya Kurban Bayramı');
        $this->puanla($puanli);

        $this->actingAs($this->admin())
            ->get(route('admin.rubric.index'))
            ->assertOk()
            ->assertSee('Tur Puanlama')
            ->assertSee('panel-sidebar-module', false)
            ->assertSee($puansiz->title)
            ->assertDontSee($pasif->title)
            ->assertDontSee($puanli->title)
            // içerik rozetleri: demo turda program/dahil/otel boş
            ->assertSee('program yok')
            ->assertSee('Gelecek tarih var')
            // sekme sayaçları: puansız 1, puanlı 1
            ->assertSeeInOrder(['Puansız<i>1</i>', 'Puanlı<i>1</i>'], false);
    }

    public function test_scored_tours_are_split_into_puanli_bayat_and_incelemede_tabs(): void
    {
        $guncel = $this->tur('Güncel Puanlı');
        $this->puanla($guncel);
        $bayat = $this->tur('Programı Değişmiş');
        $this->puanla($bayat, 'eski-hash');
        $incelemede = $this->tur('Onay Bekleyen');
        $this->puanla($incelemede, null, TourRubricScore::STATUS_NEEDS_REVIEW);
        $eksik = $this->tur('Kanıtsız Boyutlu');
        $this->puanla($eksik, null, TourRubricScore::STATUS_AUTO, ['tempo']);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.rubric.index', ['sekme' => 'puanli']))
            ->assertOk()->assertSee($guncel->title)->assertSee($eksik->title)
            ->assertDontSee($bayat->title)->assertDontSee($incelemede->title);

        $this->actingAs($admin)->get(route('admin.rubric.index', ['sekme' => 'bayat']))
            ->assertOk()->assertSee($bayat->title)->assertSee('Eski puan')
            ->assertDontSee($guncel->title);

        $this->actingAs($admin)->get(route('admin.rubric.index', ['sekme' => 'incelemede']))
            ->assertOk()->assertSee($incelemede->title)->assertSee('Onayla')
            ->assertDontSee($guncel->title);

        $this->actingAs($admin)->get(route('admin.rubric.index', ['sekme' => 'eksik-veri']))
            ->assertOk()->assertSee($eksik->title)->assertSee('tempo')
            ->assertDontSee($guncel->title);

        // Bilinmeyen sekme puansıza düşer, 500 vermez
        $this->actingAs($admin)->get(route('admin.rubric.index', ['sekme' => 'yok-boyle-sekme']))
            ->assertOk()->assertSee('Puansız aktif tur yok');
    }

    public function test_selected_tours_are_queued_once_thanks_to_the_dispatch_lock(): void
    {
        $a = $this->tur('A Turu');
        $b = $this->tur('B Turu');
        $this->kuyruguSifirla();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.rubric.queue'), ['tour_ids' => [$a->id], 'sekme' => 'puansiz'])
            ->assertRedirect(route('admin.rubric.index', ['sekme' => 'puansiz']))
            ->assertSessionHas('success');

        Queue::assertPushed(ScoreTourRubricJob::class, 1);
        Queue::assertPushed(ScoreTourRubricJob::class, fn (ScoreTourRubricJob $job) => $job->tourId === $a->id && $job->force === false);

        // Aynı tur ikinci kez: kilit tutar, job tekrar basılmaz, kullanıcı uyarılır
        $this->actingAs($admin)
            ->post(route('admin.rubric.queue'), ['tour_ids' => [$a->id], 'sekme' => 'puansiz'])
            ->assertSessionHas('warning');
        Queue::assertPushed(ScoreTourRubricJob::class, 1);

        // Hiç seçim yoksa doğrulama hatası, job yok
        $this->actingAs($admin)
            ->from(route('admin.rubric.index'))
            ->post(route('admin.rubric.queue'), ['sekme' => 'puansiz'])
            ->assertSessionHasErrors('tour_ids');
        Queue::assertPushed(ScoreTourRubricJob::class, 1);
        $this->assertSame($b->id, Tour::where('title', 'B Turu')->value('id')); // b'ye dokunulmadı
    }

    public function test_a_whole_tab_can_be_queued_without_touching_inactive_or_scored_tours(): void
    {
        $puansiz1 = $this->tur('Puansız 1');
        $puansiz2 = $this->tur('Puansız 2');
        $pasif = $this->tur('Pasif', ['is_active' => false]);
        $puanli = $this->tur('Puanlı');
        $this->puanla($puanli);
        $this->kuyruguSifirla();

        $this->actingAs($this->admin())
            ->post(route('admin.rubric.queue'), ['kapsam' => 'puansiz'])
            ->assertRedirect(route('admin.rubric.index', ['sekme' => 'puansiz']))
            ->assertSessionHas('success', fn (string $m) => str_starts_with($m, '2 tur'));

        Queue::assertPushed(ScoreTourRubricJob::class, 2);
        foreach ([$puansiz1, $puansiz2] as $tur) {
            Queue::assertPushed(ScoreTourRubricJob::class, fn (ScoreTourRubricJob $job) => $job->tourId === $tur->id);
        }
        foreach ([$pasif, $puanli] as $tur) {
            Queue::assertNotPushed(ScoreTourRubricJob::class, fn (ScoreTourRubricJob $job) => $job->tourId === $tur->id);
        }

        // Seçilen listede pasif tur olsa bile atlanır
        $this->kuyruguSifirla();
        $this->actingAs($this->admin())
            ->post(route('admin.rubric.queue'), ['tour_ids' => [$pasif->id]])
            ->assertSessionHas('warning');
        Queue::assertNothingPushed();
    }

    public function test_queue_state_is_read_from_the_jobs_and_failed_jobs_tables(): void
    {
        $kuyrukta = $this->tur('Kuyruktaki Tur');
        $basarisiz = $this->tur('Patlayan Tur');
        $bekleyen = $this->tur('Bekleyen Tur');

        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => $this->payload($kuyrukta->id), 'attempts' => 0,
            'reserved_at' => null, 'available_at' => time(), 'created_at' => time(),
        ]);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => $this->payload($basarisiz->id),
            'exception' => "OpenAI\\Exceptions\\ErrorException: Rate limit reached for gpt-5.4-mini in /srv/app/x.php:12\nStack trace:\n#0 ...",
            'failed_at' => now(),
        ]);

        $cevap = $this->actingAs($this->admin())->get(route('admin.rubric.index'))->assertOk();

        $cevap->assertSeeInOrder([$kuyrukta->title, 'Kuyrukta'], false)
            ->assertSeeInOrder([$basarisiz->title, 'Başarısız', 'Rate limit reached'], false)
            ->assertDontSee('Stack trace');

        // Özet kutusu: 1 kuyrukta, 1 başarısız
        $cevap->assertSeeInOrder(['<b>1</b><span>Kuyrukta</span><small>1 başarısız job</small>'], false);
        $this->assertSame('Bekleyen Tur', $bekleyen->fresh()->title); // satırı var, kuyruk rozeti yok: "—"
    }

    public function test_admin_tours_list_shows_a_score_badge_linking_to_the_page(): void
    {
        $puansiz = $this->tur('Rozet Puansız');
        $puanli = $this->tur('Rozet Puanlı');
        $this->puanla($puanli);

        $this->actingAs($this->admin())
            ->get(route('admin.tours'))
            ->assertOk()
            ->assertSee('<th>Puan</th>', false)
            ->assertSeeInOrder([$puanli->title, route('admin.rubric.index', ['sekme' => 'puanli']), 'Puanlı'], false)
            ->assertSeeInOrder([$puansiz->title, route('admin.rubric.index', ['sekme' => 'puansiz']), 'Puansız'], false);
    }

    public function test_sidebar_badge_counts_unscored_active_tours(): void
    {
        $this->tur('Puansız A');
        $this->tur('Puansız B');
        $this->tur('Pasif', ['is_active' => false]);
        $this->puanla($this->tur('Puanlı'));

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Tur Puanlama', '<span class="p-sb-rozet">2</span>'], false);
    }

    public function test_command_reports_the_same_numbers_as_the_page_and_respects_dry(): void
    {
        $this->tur('Puansız');
        $bayat = $this->tur('Bayat');
        $this->puanla($bayat, 'eski-hash');
        $this->puanla($this->tur('Güncel'));
        $this->kuyruguSifirla();

        $this->artisan('app:score-tours-rubric', ['--dry' => true])
            ->expectsOutputToContain('aktif tur: 3 | puanlı: 2 | YAYINLANABİLİR (chat kart gösterebilir): 2 | editör onayı bekleyen: 0 | puansız: 1 | bayat: 1')
            ->expectsOutputToContain('2 tur kuyruğa alınacaktı (--dry)')
            ->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('app:score-tours-rubric')
            ->expectsOutputToContain('2 tur rubrik puanlaması için kuyruğa alındı')
            ->assertSuccessful();
        Queue::assertPushed(ScoreTourRubricJob::class, 2);
    }

    public function test_non_admins_cannot_open_or_queue(): void
    {
        $musteri = User::factory()->create(['role' => 'customer']);
        $tur = $this->tur('Gizli');

        // Misafir önce: actingAs aynı test içinde sonraki isteklere de yapışır
        $this->get(route('admin.rubric.index'))->assertRedirect(route('login'));
        $this->actingAs($musteri)->get(route('admin.rubric.index'))->assertForbidden();
        $this->actingAs($musteri)->post(route('admin.rubric.queue'), ['tour_ids' => [$tur->id]])->assertForbidden();
    }
}
