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
}
