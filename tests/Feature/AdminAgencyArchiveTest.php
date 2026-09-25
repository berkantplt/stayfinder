<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin acenta "Sil" = arşiv (soft delete, turlarıyla birlikte) + geri alma.
 */
class AdminAgencyArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    private function makeAgency(string $name = 'Arşiv Acenta', string $email = 'arsiv@example.com'): Agency
    {
        return Agency::create([
            'name' => $name,
            'email' => $email,
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);
    }

    private function makeAgencyUser(Agency $agency, string $email = 'yetkili@example.com'): User
    {
        return User::create([
            'name' => 'Acenta Yetkilisi',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);
    }

    private function makeTour(Agency $agency, string $title = 'Arşiv Test Turu'): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'title' => $title,
            'destination' => 'Kapadokya',
            'description' => 'Acenta arşiv testi.',
            'price' => 4500,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
        ]);
    }

    public function test_admin_archives_agency_together_with_its_tours(): void
    {
        $agency = $this->makeAgency();
        $tour = $this->makeTour($agency);

        $this->actingAs($this->admin)
            ->delete(route('admin.agencies.archive', $agency))
            ->assertRedirect(route('admin.agencies'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('agencies', ['id' => $agency->id]);
        $this->assertSoftDeleted('tours', ['id' => $tour->id]);

        // Public tur sayfası artık 404 (rota bağlama arşivli turu getirmez)
        $this->get(route('tours.show', $tour->slug))->assertNotFound();
        $this->get(route('tours.index'))->assertOk()->assertDontSee('Arşiv Test Turu');
    }

    public function test_archived_agency_user_lands_on_removed_notice_instead_of_panel(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);

        $agency->archiveWithTours();

        $this->actingAs($user)
            ->get(route('agency.dashboard'))
            ->assertRedirect(route('agency.application.status'));

        $this->actingAs($user)
            ->get(route('agency.application.status'))
            ->assertOk()
            ->assertSee('Acenta Hesabınız Kaldırıldı');
    }

    public function test_agency_list_hides_archived_by_default_and_shows_them_under_filter(): void
    {
        $agency = $this->makeAgency();
        $agency->archiveWithTours();

        $this->actingAs($this->admin)
            ->get(route('admin.agencies'))
            ->assertOk()
            ->assertDontSee('Arşiv Acenta');

        $this->actingAs($this->admin)
            ->get(route('admin.agencies', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Arşiv Acenta')
            ->assertSee('Geri Al')
            ->assertSee(route('admin.agencies.restore', $agency), false);
    }

    public function test_archived_agency_detail_page_still_opens_with_restore_button(): void
    {
        $agency = $this->makeAgency();
        $agency->archiveWithTours();

        $this->actingAs($this->admin)
            ->get(route('admin.agencies.show', $agency))
            ->assertOk()
            ->assertSee('Arşivde')
            ->assertSee('Arşivden Geri Al');
    }

    public function test_restore_brings_back_agency_and_cascade_archived_tours_only(): void
    {
        $agency = $this->makeAgency();
        $oncedenArsivli = $this->makeTour($agency, 'Önceden Arşivlenmiş Tur');
        $yayinda = $this->makeTour($agency, 'Yayındaki Tur');

        // Acenta kendi turunu daha önce arşivlemiş olsun
        $oncedenArsivli->delete();
        Tour::withTrashed()->whereKey($oncedenArsivli->id)->update(['deleted_at' => now()->subDay()]);

        $agency->archiveWithTours();
        $this->assertSoftDeleted('tours', ['id' => $yayinda->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.agencies.restore', $agency))
            ->assertRedirect(route('admin.agencies.show', $agency))
            ->assertSessionHas('success');

        $this->assertNull($agency->fresh()->deleted_at);
        $this->assertNull(Tour::withTrashed()->find($yayinda->id)->deleted_at);
        $this->assertNotNull(Tour::withTrashed()->find($oncedenArsivli->id)->deleted_at, 'Acentanın kendi arşivlediği tur geri gelmemeli');
    }

    public function test_new_agency_cannot_take_archived_agency_slug(): void
    {
        $agency = $this->makeAgency('Öz Tur');
        $this->assertSame('oz-tur', $agency->slug);

        $agency->archiveWithTours();

        $yeni = $this->makeAgency('Öz Tur', 'yeni@example.com');

        $this->assertNotSame('oz-tur', $yeni->slug);
        $this->assertStringStartsWith('oz-tur-', $yeni->slug);
    }

    public function test_non_admin_cannot_archive_or_restore(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);

        $this->actingAs($user)
            ->delete(route('admin.agencies.archive', $agency))
            ->assertForbidden();

        $this->assertNull($agency->fresh()->deleted_at);

        $agency->archiveWithTours();

        $this->actingAs($user)
            ->post(route('admin.agencies.restore', $agency))
            ->assertForbidden();
    }
}
