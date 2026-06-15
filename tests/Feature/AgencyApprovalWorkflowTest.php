<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgencyApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_agency_is_redirected_to_application_status_after_login(): void
    {
        $agency = Agency::create([
            'name' => 'Bekleyen Acenta',
            'email' => 'bekleyen@example.com',
            'is_active' => false,
            'approval_status' => Agency::STATUS_PENDING,
        ]);

        $user = User::create([
            'name' => 'Bekleyen Kullanici',
            'email' => 'bekleyen@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('agency.application.status'));

        $this->actingAs($user)
            ->get(route('agency.dashboard'))
            ->assertRedirect(route('agency.application.status'));
    }

    public function test_admin_can_approve_pending_agency_application(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $agency = Agency::create([
            'name' => 'Onay Bekleyen',
            'email' => 'onay@example.com',
            'is_active' => false,
            'approval_status' => Agency::STATUS_PENDING,
        ]);

        $agencyUser = User::create([
            'name' => 'Acenta Yetkilisi',
            'email' => 'onay@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.agency-applications.approve', $agency), [
                'approval_notes' => 'Basvuru uygun bulundu.',
            ])
            ->assertRedirect(route('admin.agency-applications'));

        $agency->refresh();

        $this->assertTrue($agency->is_active);
        $this->assertSame(Agency::STATUS_APPROVED, $agency->approval_status);
        $this->assertSame($admin->id, $agency->approved_by);
        $this->assertNotNull($agency->approved_at);
        $this->assertSame('Basvuru uygun bulundu.', $agency->approval_notes);

        $this->actingAs($agencyUser)
            ->get(route('agency.category-licenses.index'))
            ->assertOk();
    }

    public function test_rejected_agency_cannot_be_activated_via_toggle(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $agency = Agency::create([
            'name' => 'Reddedilen Acenta',
            'email' => 'red@example.com',
            'is_active' => false,
            'approval_status' => Agency::STATUS_REJECTED,
            'legacy_category_access' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.agencies.toggle', $agency))
            ->assertRedirect(route('admin.agencies'))
            ->assertSessionHasErrors();

        $this->assertFalse($agency->fresh()->is_active);
    }

    public function test_pending_agency_cannot_be_activated_via_toggle(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin3@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $agency = Agency::create([
            'name' => 'Bekleyen Toggle Acenta',
            'email' => 'bekleyen-toggle@example.com',
            'is_active' => false,
            'approval_status' => Agency::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.agencies.toggle', $agency))
            ->assertSessionHasErrors();

        $this->assertFalse($agency->fresh()->is_active);
    }

    public function test_approved_agency_can_be_toggled_both_ways(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin4@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);

        $agency = Agency::create([
            'name' => 'Onaylı Acenta',
            'email' => 'onayli@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        // Pasifleştirme her zaman serbest
        $this->actingAs($admin)->post(route('admin.agencies.toggle', $agency));
        $this->assertFalse($agency->fresh()->is_active);

        // Onaylı acenta tekrar aktifleştirilebilir
        $this->actingAs($admin)->post(route('admin.agencies.toggle', $agency));
        $this->assertTrue($agency->fresh()->is_active);
    }
}
