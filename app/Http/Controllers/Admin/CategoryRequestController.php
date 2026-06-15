<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryRequest;
use App\Notifications\CategoryRequestDecisionNotification;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequestController extends Controller
{
    public function index()
    {
        $pendingRequests = CategoryRequest::pending()
            ->with('agency')
            ->orderBy('created_at')
            ->get();

        $recentRequests = CategoryRequest::whereIn('status', [
            CategoryRequest::STATUS_APPROVED,
            CategoryRequest::STATUS_REJECTED,
        ])
            ->with(['agency', 'parent', 'createdCategory', 'reviewer'])
            ->orderByDesc('reviewed_at')
            ->limit(15)
            ->get();

        $parentCategories = Category::parents()->orderBy('sort_order')->orderBy('name')->get();
        $categoryLicensingReady = CategoryLicensing::schemaReady();

        return view('admin.category-requests.index', compact(
            'pendingRequests',
            'recentRequests',
            'parentCategories',
            'categoryLicensingReady'
        ));
    }

    /**
     * Talebi onayla: seçilen üst kategorinin altında fiyatlı bir alt kategori
     * oluştur, talebi onaylı işaretle ve acentaya bildir.
     */
    public function approve(Request $request, CategoryRequest $categoryRequest)
    {
        if (! $categoryRequest->isPending()) {
            return back()->withErrors('Bu talep zaten sonuçlandırılmış.');
        }

        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'parent_id' => ['required', Rule::exists('categories', 'id')->whereNull('parent_id')],
        ];

        if (CategoryLicensing::schemaReady()) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request, $categoryRequest) {
            $nextSort = (int) Category::where('parent_id', $validated['parent_id'])->max('sort_order') + 1;

            $category = Category::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'icon' => $validated['icon'] ?? null,
                'parent_id' => $validated['parent_id'],
                'monthly_price' => CategoryLicensing::schemaReady()
                    ? round((float) $request->input('monthly_price', 0), 2)
                    : 0,
                'sort_order' => $nextSort,
                'is_active' => true,
            ]);

            $categoryRequest->update([
                'status' => CategoryRequest::STATUS_APPROVED,
                'parent_id' => $validated['parent_id'],
                'created_category_id' => $category->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'admin_note' => null,
            ]);
        });

        $this->notifyAgency($categoryRequest->fresh());

        return redirect()->route('admin.category-requests.index')
            ->with('success', 'Talep onaylandı ve kategori oluşturuldu.');
    }

    public function reject(Request $request, CategoryRequest $categoryRequest)
    {
        if (! $categoryRequest->isPending()) {
            return back()->withErrors('Bu talep zaten sonuçlandırılmış.');
        }

        $validated = $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $categoryRequest->update([
            'status' => CategoryRequest::STATUS_REJECTED,
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyAgency($categoryRequest->fresh());

        return redirect()->route('admin.category-requests.index')
            ->with('success', 'Talep reddedildi.');
    }

    private function notifyAgency(CategoryRequest $categoryRequest): void
    {
        $users = $categoryRequest->agency?->users ?? collect();

        if ($users->isNotEmpty()) {
            Notification::send($users, new CategoryRequestDecisionNotification($categoryRequest));
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 1;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
