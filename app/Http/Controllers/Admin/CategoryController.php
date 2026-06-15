<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent', 'children')->orderBy('sort_order')->get();
        $parentCategories = Category::parents()->orderBy('name')->get();
        $categoryLicensingReady = CategoryLicensing::schemaReady();

        return view('admin.categories.index', compact('categories', 'parentCategories', 'categoryLicensingReady'));
    }

    /**
     * Ayrı "Üst Kategori Yönetimi" sayfası — sadece ana (parent) kategoriler.
     */
    public function parents()
    {
        $parentCategories = Category::parents()
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $categoryLicensingReady = CategoryLicensing::schemaReady();

        return view('admin.categories.parents', compact('parentCategories', 'categoryLicensingReady'));
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ];

        if (CategoryLicensing::schemaReady()) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        if (CategoryLicensing::schemaReady()) {
            $validated['monthly_price'] = round((float) $validated['monthly_price'], 2);
        }

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori oluşturuldu.');
    }

    /**
     * Üst (ana) kategori oluşturur — parent_id her zaman null. "Üst Kategori
     * Yönetimi" panelindeki ayrı form bunu kullanır; alt kategori seçtirmez.
     */
    public function storeParent(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ];

        if (CategoryLicensing::schemaReady()) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['parent_id'] = null;
        if (CategoryLicensing::schemaReady()) {
            $validated['monthly_price'] = round((float) $validated['monthly_price'], 2);
        }

        Category::create($validated);

        return redirect()->route('admin.categories.parents')->with('success', 'Üst kategori oluşturuldu.');
    }

    public function update(Request $request, Category $category)
    {
        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
        ];

        if (CategoryLicensing::schemaReady()) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        if (CategoryLicensing::schemaReady()) {
            $validated['monthly_price'] = round((float) $validated['monthly_price'], 2);
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category)
    {
        if ($category->children()->count() > 0 || $category->tours()->count() > 0) {
            return redirect()->route('admin.categories.index')
                ->withErrors('Bu kategoriye bağlı alt kategoriler veya turlar olduğu için silinemez.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
    }

    public function toggle(Category $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori durumu güncellendi.');
    }
}
