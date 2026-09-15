<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D11 — Mobil alt sekme "Hesabım": kupon/bildirim/kayıtlı arama sayfalarında da
 * aktif kalır (hesap ana ekranının sekmeleri), okunmamış bildirim rozeti taşır.
 */
class MobileTabbarAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_hesap_sayfalarinda_hesabim_sekmesi_aktif(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        foreach (['customer.coupons.index', 'notifications.index', 'customer.saved-searches.index', 'profile.show'] as $route) {
            $html = $this->actingAs($user)->get(route($route))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<a href="[^"]*\/profilim" class="active"[^>]*>/', $html, $route);
            $this->assertStringContainsString('Hesabım', $html);
        }

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/<a href="[^"]*\/profilim" class="active"/', $html);
    }

    public function test_okunmamis_bildirim_rozeti_sekmede_gorunur(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $user->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'Test', 'message' => 'Test'],
        ]);

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee('<span class="m-tab-rozet">1</span>', false);
    }
}
