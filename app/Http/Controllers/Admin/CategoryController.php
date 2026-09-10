<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $extraSlotReady = CategoryLicensing::slotSchemaReady();

        // Üst kategori başına "sonraki sıra no" = mevcut en yüksek alt kategori sırası + 1
        // (alt kategorisi olmayan üst kategoriler haritada yer almaz → JS varsayılanı 1)
        $nextSortByParent = Category::query()
            ->whereNotNull('parent_id')
            ->selectRaw('parent_id, MAX(sort_order) as max_sort')
            ->groupBy('parent_id')
            ->pluck('max_sort', 'parent_id')
            ->map(fn ($max) => (int) $max + 1);

        return view('admin.categories.index', compact('categories', 'parentCategories', 'categoryLicensingReady', 'extraSlotReady', 'nextSortByParent'));
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

        if (CategoryLicensing::slotSchemaReady()) {
            $rules['extra_tour_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['monthly_price'] = CategoryLicensing::schemaReady()
            ? round((float) $validated['monthly_price'], 2)
            : 0;

        if (CategoryLicensing::slotSchemaReady()) {
            $validated['extra_tour_price'] = round((float) $validated['extra_tour_price'], 2);
        }

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
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
        ]);
        unset($validated['image_file']);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['parent_id'] = null;
        $validated['monthly_price'] = 0; // üst kategoriler fiyatsız (sadece gruplama)

        if (CategoryLicensing::slotSchemaReady()) {
            $validated['extra_tour_price'] = 0;
        }

        $category = Category::create($validated);
        $this->applyImage($request, $category);

        return redirect()->route('admin.categories.parents')->with('success', 'Üst kategori oluşturuldu.');
    }

    /**
     * Kategori görseli (ana sayfa büyük kartları): yeni dosya yüklenirse eski
     * silinir; "kaldır" işaretliyse görsel boşaltılır. Dış URL'ler dosya değildir,
     * Storage'dan silinmez.
     */
    private function applyImage(Request $request, Category $category): void
    {
        $eski = $category->image;

        if ($request->hasFile('image_file')) {
            $yol = $request->file('image_file')->store('categories', 'public');
            $category->forceFill(['image' => $yol])->save();
        } elseif ($request->boolean('remove_image')) {
            $category->forceFill(['image' => null])->save();
        } else {
            return;
        }

        if ($eski && ! str_starts_with($eski, 'http')) {
            Storage::disk('public')->delete($eski);
        }
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
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:3072',
            'remove_image' => 'nullable|boolean',
        ];

        if (CategoryLicensing::schemaReady() && $isChild) {
            $rules['monthly_price'] = 'required|numeric|min:0';
        }

        if (CategoryLicensing::slotSchemaReady() && $isChild) {
            $rules['extra_tour_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);
        unset($validated['image_file'], $validated['remove_image']);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['monthly_price'] = $isChild ? round((float) $request->input('monthly_price', 0), 2) : 0;

        if (CategoryLicensing::slotSchemaReady()) {
            $validated['extra_tour_price'] = $isChild ? round((float) $request->input('extra_tour_price', 0), 2) : 0;
        }

        $category->update($validated);
        $this->applyImage($request, $category);

        return redirect()->route($isChild ? 'admin.categories.index' : 'admin.categories.parents')->with('success', 'Kategori güncellendi.');
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
