<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * "Alt Kategori Yönetimi" sayfası — yalnızca bir üst kategoriye bağlı,
     * fiyatlı alt kategoriler. Üst kategoriler ayrı sayfada (parents).
     */
    public function index()
    {
        // Önce bağlı olduğu üst kategoriye göre (üst kategorinin sırası, sonra adı),
        // ardından kendi içinde sıra numarası/adına göre — farklı grupların turları karışmasın
        $categories = Category::with('parent')
            ->whereNotNull('categories.parent_id')
            ->leftJoin('categories as parent_categories', 'categories.parent_id', '=', 'parent_categories.id')
            ->orderBy('parent_categories.sort_order')
            ->orderBy('parent_categories.name')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->select('categories.*')
            ->get();
        $parentCategories = Category::parents()->orderBy('sort_order')->orderBy('name')->get();
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

    /**
     * Alt kategori oluşturur — bir üst kategoriye bağlı (parent_id zorunlu, üst
     * kategori top-level olmalı) ve fiyatlı. Üst kategori ekleme storeParent ile.
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'parent_id' => ['required', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'sort_order' => 'nullable|integer',
        ];

        if (CategoryLicensing::schemaReady()) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['monthly_price'] = CategoryLicensing::schemaReady()
            ? round((float) $validated['monthly_price'], 2)
            : 0;

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', 'Alt kategori oluşturuldu.');
    }

    /**
     * Üst (ana) kategori oluşturur — parent_id her zaman null. "Üst Kategori
     * Yönetimi" panelindeki ayrı form bunu kullanır; alt kategori seçtirmez.
     */
    public function storeParent(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['parent_id'] = null;
        $validated['monthly_price'] = 0; // üst kategoriler fiyatsız (sadece gruplama)

        Category::create($validated);

        return redirect()->route('admin.categories.parents')->with('success', 'Üst kategori oluşturuldu.');
    }

    public function update(Request $request, Category $category)
    {
        // Fiyat yalnızca alt kategorilerde; üst (parent_id boş) kategoriler fiyatsız gruptur.
        $isChild = $request->filled('parent_id');

        $rules = [
            'name' => 'required|string|max:100',
            'icon' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            // Bağlanılan üst kategori top-level olmalı (3. seviye iç içe geçmeyi engelle)
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'sort_order' => 'nullable|integer',
        ];

        if (CategoryLicensing::schemaReady() && $isChild) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['monthly_price'] = $isChild ? round((float) $request->input('monthly_price', 0), 2) : 0;

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
