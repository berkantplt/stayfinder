<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\CategoryRequest;
use App\Models\User;
use App\Notifications\CategoryRequestDecisionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CategoryRequestTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Talep Acenta',
            'slug' => 'talep-acenta',
            'email' => 'talep@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => false,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeParent(): Category
    {
        return Category::create([
            'name' => 'Yurt Dışı',
            'slug' => 'yurt-disi',
            'monthly_price' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_agency_can_create_pending_request(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.category-requests.store'), [
                'requested_name' => 'Latin Amerika Turları',
                'note' => 'Müşterilerden talep geliyor.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('category_requests', [
            'agency_id' => $this->agency->id,
            'requested_name' => 'Latin Amerika Turları',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);
    }

    public function test_duplicate_pending_request_is_blocked(): void
    {
        CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Latin Amerika',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->agencyUser)
            ->post(route('agency.category-requests.store'), ['requested_name' => 'latin amerika'])
            ->assertSessionHasErrors();

        $this->assertSame(1, CategoryRequest::count());
    }

    public function test_admin_approve_creates_child_category_and_notifies_agency(): void
    {
        Notification::fake();

        $parent = $this->makeParent();
        $req = CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Latin Amerika',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.category-requests.approve', $req), [
                'name' => 'Latin Amerika Turları',
                'icon' => '🌎',
                'parent_id' => $parent->id,
                'monthly_price' => 3000,
            ])
            ->assertRedirect(route('admin.category-requests.index'));

        // Alt kategori, seçilen üst kategorinin altında ve fiyatlı oluştu
        $this->assertDatabaseHas('categories', [
            'name' => 'Latin Amerika Turları',
            'parent_id' => $parent->id,
            'monthly_price' => '3000.00',
            'is_active' => 1,
        ]);

        $req->refresh();
        $this->assertSame(CategoryRequest::STATUS_APPROVED, $req->status);
        $this->assertSame($parent->id, $req->parent_id);
        $this->assertNotNull($req->created_category_id);
        $this->assertSame($this->admin->id, $req->reviewed_by);

        Notification::assertSentTo($this->agencyUser, CategoryRequestDecisionNotification::class);
    }

    public function test_approve_requires_parent_and_price(): void
    {
        $this->makeParent();
        $req = CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Test',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.category-requests.approve', $req), ['name' => 'Test'])
            ->assertSessionHasErrors(['parent_id', 'monthly_price']);

        $this->assertSame(CategoryRequest::STATUS_PENDING, $req->fresh()->status);
    }

    public function test_approve_rejects_non_top_level_parent(): void
    {
        $parent = $this->makeParent();
        $child = Category::create([
            'name' => 'Alt', 'slug' => 'alt', 'parent_id' => $parent->id, 'monthly_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        $req = CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Test',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.category-requests.approve', $req), [
                'name' => 'Test',
                'parent_id' => $child->id, // alt kategori → reddedilmeli
                'monthly_price' => 2000,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_admin_reject_marks_rejected_and_notifies(): void
    {
        Notification::fake();

        $req = CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Uygunsuz',
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.category-requests.reject', $req), ['admin_note' => 'Zaten mevcut.'])
            ->assertRedirect(route('admin.category-requests.index'));

        $req->refresh();
        $this->assertSame(CategoryRequest::STATUS_REJECTED, $req->status);
        $this->assertSame('Zaten mevcut.', $req->admin_note);

        Notification::assertSentTo($this->agencyUser, CategoryRequestDecisionNotification::class);
    }

    public function test_already_decided_request_cannot_be_reapproved(): void
    {
        $parent = $this->makeParent();
        $req = CategoryRequest::create([
            'agency_id' => $this->agency->id,
            'requested_name' => 'Karar Verilmiş',
            'status' => CategoryRequest::STATUS_REJECTED,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.category-requests.approve', $req), [
                'name' => 'Karar Verilmiş',
                'parent_id' => $parent->id,
                'monthly_price' => 2000,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(CategoryRequest::STATUS_REJECTED, $req->fresh()->status);
        $this->assertDatabaseMissing('categories', ['name' => 'Karar Verilmiş']);
    }

    public function test_non_admin_cannot_access_admin_requests(): void
    {
        $this->actingAs($this->agencyUser)
            ->get(route('admin.category-requests.index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_create_request(): void
    {
        $this->post(route('agency.category-requests.store'), ['requested_name' => 'X'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, CategoryRequest::count());
    }
}
