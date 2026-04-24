<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_can_register_from_public_form(): void
    {
        $response = $this->post(route('register.post'), [
            'account_type' => 'agency',
            'agency_name' => 'Yeni Dunya Travel',
            'name' => 'Ayse Yilmaz',
            'email' => 'acenta@example.com',
            'phone' => '05551234567',
            'website_url' => 'https://acentatest.example.com',
            'description' => 'Kurumsal acenta kaydi.',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $agency = Agency::first();
        $user = User::first();

        $response->assertRedirect(route('agency.application.status'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($agency);
        $this->assertNotNull($user);
        $this->assertSame('agency', $user->role);
        $this->assertSame($agency->id, $user->agency_id);
        $this->assertFalse((bool) $agency->legacy_category_access);
        $this->assertFalse((bool) $agency->is_active);
        $this->assertSame(Agency::STATUS_PENDING, $agency->approval_status);

        $this->assertDatabaseHas('agencies', [
            'name' => 'Yeni Dunya Travel',
            'email' => 'acenta@example.com',
            'phone' => '05551234567',
            'approval_status' => Agency::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Ayse Yilmaz',
            'email' => 'acenta@example.com',
            'role' => 'agency',
        ]);
    }
}
