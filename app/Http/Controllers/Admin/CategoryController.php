<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgencyCategoryOrderItem;
use App\Models\Category;
use App\Support\CategoryLicensing;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * B5 — Kategori silme korumaları.
     *
     * Eskiden yalnız alt kategori ve (arşivdekiler hariç) tur sayısına bakılıyordu;
     * agency_category_subscriptions.category_id cascadeOnDelete olduğundan silme,
     * acentanın parasıyla aldığı aboneliği (başlangıç/bitiş/fiyat = denetim izi)
     * sessizce yok ediyordu. Panelde "0 tur" görünen ama aboneliği olan kategori
     * tam bu tuzaktı. Erişimi kapatmanın yolu silmek değil pasife almak (toggle).
     *
     * - Arşivdeki (soft-deleted) turlar da sayılır: geri alınınca kategorisiz kalmasın.
     * - Abonelik satırı (her durumda) ya da sipariş kalemi olan kategori silinmez:
     *   pending sipariş ödendiğinde kategori yoksa lisans açılamazdı.
     * - Sayım ve silme tek transaction'da, kategori satırı kilitli: aynı anda gelen
     *   iyzico callback'i ile yarış kapanır. DB tarafında FK artık restrictOnDelete
     *   (migration 2026_09_15_110000) — kod atlansa bile cascade kaybı olmaz.
     */
    public function destroy(Category $category)
    {
        $turSayisi = $category->tours()->withTrashed()->count();
        if ($category->children()->count() > 0 || $turSayisi > 0) {
            $arsiv = $category->tours()->onlyTrashed()->count();

            return redirect()->route('admin.categories.index')
                ->withErrors('Bu kategoriye bağlı alt kategoriler veya turlar olduğu için silinemez.'
                    .($arsiv > 0 ? " ({$arsiv} tur arşivde — 30 gün içinde geri alınabilir.)" : ''));
        }

        if (! CategoryLicensing::schemaReady()) {
            $category->delete();

            return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
        }

        try {
            $engel = DB::transaction(function () use ($category) {
                $kilitli = Category::whereKey($category->id)->lockForUpdate()->firstOrFail();

                $abonelik = $kilitli->agencyCategorySubscriptions()->count();
                $kalem = AgencyCategoryOrderItem::where('category_id', $kilitli->id)->count();

                if ($abonelik > 0 || $kalem > 0) {
                    $aktif = $kilitli->activeAgencyCategorySubscriptions()->count();

                    return sprintf(
                        '%s silinemez: %d abonelik kaydı (%d aktif) ve %d sipariş kalemi var. Acenta erişimini kapatmak için kategoriyi pasife alın; abonelik ve sipariş geçmişi denetim izi olarak kalmalı.',
                        $kilitli->name,
                        $abonelik,
                        $aktif,
                        $kalem
                    );
                }

                $kilitli->delete();

                return null;
            });
        } catch (QueryException $e) {
            // restrictOnDelete FK yarış hâlinde devreye girdi (aynı anda abonelik yazıldı)
            return redirect()->route('admin.categories.index')
                ->withErrors($category->name.' silinemedi: aynı anda bu kategoriye bir abonelik/sipariş yazıldı. Sayfayı yenileyip tekrar deneyin.');
        }

        if ($engel !== null) {
            return redirect()->route('admin.categories.index')->withErrors($engel);
        }

        return redirect()->route('admin.categories.index')->with('success', 'Kategori silindi.');
    }

    public function toggle(Category $category)
    {
        $category->update(['is_active' => ! $category->is_active]);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori durumu güncellendi.');
    }
}
