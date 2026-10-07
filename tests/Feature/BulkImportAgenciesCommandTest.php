<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BulkImportAgenciesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $parent = Category::create(['name' => 'Yurt İçi Kültür Turları', 'slug' => 'yurt-ici-kultur-turlari', 'is_active' => true, 'monthly_price' => 0]);
        Category::create(['name' => 'GAP Turları', 'slug' => 'gap-turlari', 'parent_id' => $parent->id, 'is_active' => true, 'monthly_price' => 1500]);
        Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $parent->id, 'is_active' => true, 'monthly_price' => 2000]);

        $this->file = sys_get_temp_dir().'/bulk-agencies-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function writeDefinitions(array $agencies): void
    {
        file_put_contents($this->file, json_encode(['agencies' => $agencies], JSON_UNESCAPED_UNICODE));
    }

    private function malitur(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'malitur',
            'name' => 'Malitur',
            'website_url' => 'https://malitur.com',
            'phone' => '0555 111 11 11',
            'email' => 'malitur@gmail.com',
            'panel_email' => 'malitur@gmail.com',
            'description' => 'Test acentası',
            'categories' => ['gap-turlari', 'balkan-turlari'],
        ], $overrides);
    }

    public function test_creates_agency_user_and_one_year_subscriptions_without_sending_mail(): void
    {
        Notification::fake();
        Mail::fake();
        $this->writeDefinitions([$this->malitur()]);

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'test-2026-10', '--password' => 'Gizli-Parola-1'])
            ->expectsOutputToContain('yaratıldı')
            ->assertSuccessful();

        $agency = Agency::where('slug', 'malitur')->firstOrFail();
        $this->assertSame(Agency::STATUS_APPROVED, $agency->approval_status);
        $this->assertNotNull($agency->approved_at);
        $this->assertTrue($agency->is_active);
        $this->assertFalse($agency->legacy_category_access);
        $this->assertSame('test-2026-10', $agency->import_batch);
        $this->assertSame('https://malitur.com', $agency->website_url);

        $user = User::where('email', 'malitur@gmail.com')->firstOrFail();
        $this->assertSame('agency', $user->role);
        $this->assertSame($agency->id, $user->agency_id);
        $this->assertNotNull($user->email_verified_at, 'Doğrulama maili atılmasın diye doğrulanmış açılır');
        $this->assertTrue(Hash::check('Gizli-Parola-1', $user->password));

        $subs = AgencyCategorySubscription::where('agency_id', $agency->id)->get();
        $this->assertCount(2, $subs);
        foreach ($subs as $sub) {
            $this->assertSame(AgencyCategorySubscription::STATUS_ACTIVE, $sub->status);
            $this->assertTrue($sub->is_active);
            $this->assertSame(now()->startOfDay()->addMonths(12)->toDateString(), $sub->expires_at->toDateString());
            $this->assertSame(8, (int) $sub->extra_tour_slots);
            $this->assertNotNull($sub->last_order_id);
        }
        $this->assertTrue($agency->hasCategoryAccess(Category::where('slug', 'gap-turlari')->first()));
        $this->assertSame(10, $agency->categoryTourLimit(Category::where('slug', 'gap-turlari')->first()));

        $orders = AgencyCategoryOrder::where('agency_id', $agency->id)->get();
        $this->assertCount(2, $orders);
        $this->assertSame(AgencyCategoryOrder::PROVIDER_MANUAL, $orders[0]->payment_provider);
        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $orders[0]->status);
        $this->assertEquals(0, (float) $orders[0]->subtotal);

        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->writeDefinitions([$this->malitur()]);
        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1', '--password' => 'Gizli-Parola-1'])->assertSuccessful();

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1', '--password' => 'Gizli-Parola-1'])
            ->expectsOutputToContain('mevcut')
            ->assertSuccessful();

        $this->assertSame(1, Agency::count());
        $this->assertSame(1, User::where('role', 'agency')->count());
        $this->assertSame(2, AgencyCategorySubscription::count());
        $this->assertSame(2, AgencyCategoryOrder::count(), 'Aktif aboneliğe yeniden sipariş yazılmaz');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->writeDefinitions([$this->malitur()]);

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1', '--dry' => true])
            ->expectsOutputToContain('yaratılacak')
            ->assertSuccessful();

        $this->assertSame(0, Agency::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, AgencyCategorySubscription::count());
    }

    public function test_unknown_or_parent_category_fails_without_writing(): void
    {
        $this->writeDefinitions([
            $this->malitur(['categories' => ['gap-turlari', 'yok-boyle-kategori']]),
            $this->malitur(['slug' => 'ikinci', 'name' => 'İkinci', 'panel_email' => 'ikinci@gmail.com', 'categories' => ['yurt-ici-kultur-turlari']]),
        ]);

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1', '--password' => 'Gizli-Parola-1'])
            ->expectsOutputToContain('yok-boyle-kategori')
            ->expectsOutputToContain('üst kategori')
            ->assertFailed();

        $this->assertSame(0, Agency::count());
        $this->assertSame(0, User::count());
    }

    public function test_batch_is_required_and_generated_password_is_shown_once(): void
    {
        $this->writeDefinitions([$this->malitur()]);

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file])
            ->expectsOutputToContain('--batch zorunlu')
            ->assertFailed();

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1'])
            ->expectsOutputToContain('Üretilen parolalar bir daha gösterilmez')
            ->assertSuccessful();

        $user = User::where('email', 'malitur@gmail.com')->firstOrFail();
        $this->assertNotNull($user->password_set_at);
    }

    public function test_panel_email_bound_to_another_agency_is_rejected(): void
    {
        $other = Agency::create(['name' => 'Başka', 'slug' => 'baska', 'is_active' => true]);
        User::factory()->create(['email' => 'malitur@gmail.com', 'role' => 'agency', 'agency_id' => $other->id]);
        $this->writeDefinitions([$this->malitur()]);

        $this->artisan('app:bulk-import-agencies', ['file' => $this->file, '--batch' => 'b1', '--password' => 'Gizli-Parola-1'])
            ->expectsOutputToContain('başka bir hesaba bağlı')
            ->assertFailed();

        $this->assertNull(Agency::where('slug', 'malitur')->first());
    }
}
