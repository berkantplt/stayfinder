<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryBanner;
use App\Support\CategoryHero;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Kategori Banner Yönetimi — kategori landing sayfalarının (/balkan-turlari)
 * hero alanı. Ana sayfa karuselini yöneten BannerController'dan AYRI modül.
 *
 * Sayfa yapısı: genel varsayılan + üst kategori akordeonu (tek seferde biri
 * açık) → üst kategorinin kendi sayfası + alt kategoriler, her birinin
 * banner'ı ve formu. Çözümleme sırası App\Support\CategoryHero'da.
 */
class CategoryBannerController extends Controller
{
    private const DISK_DIR = 'category-banners';

    public function index()
    {
        $parents = Category::query()
            ->parents()
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $banners = CategoryBanner::query()->get()->keyBy(fn (CategoryBanner $b) => $b->category_id ?? 0);

        return view('admin.category-banners', [
            'parents' => $parents,
            'banners' => $banners,
            'genel' => $banners->get(0),
            'overlay' => [
                'rgb' => CategoryHero::OVERLAY_RGB,
                'left' => CategoryHero::LEFT_BOOST,
                'right' => CategoryHero::RIGHT_FACTOR,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id'), Rule::unique('category_banners', 'category_id')],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ] + $this->textRules(), $this->messages());

        $categoryId = $validated['category_id'] ?? null;

        // "nullable" null'da unique'i çalıştırmaz: genel varsayılan tekliği elle.
        if ($categoryId === null && CategoryBanner::whereNull('category_id')->exists()) {
            return back()->withInput()->withErrors(['category_id' => 'Genel varsayılan banner zaten var; onu düzenleyin.']);
        }

        $banner = CategoryBanner::create([
            'category_id' => $categoryId,
            'image' => $request->file('image')->store(self::DISK_DIR, 'public'),
        ] + $this->textValues($validated));

        return redirect()->route('admin.category-banners.index')
            ->with('success', $this->label($banner).' banner\'ı eklendi.')
            ->with('kb_open', $this->openKey($banner));
    }

    public function update(Request $request, CategoryBanner $categoryBanner)
    {
        $validated = $request->validate([
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ] + $this->textRules(), $this->messages());

        $yeniDosya = null;
        if ($request->hasFile('image')) {
            $yeniDosya = $request->file('image')->store(self::DISK_DIR, 'public');
        }

        $eski = $categoryBanner->image;
        $categoryBanner->update($this->textValues($validated) + ($yeniDosya ? ['image' => $yeniDosya] : []));

        if ($yeniDosya && $eski && Storage::disk('public')->exists($eski)) {
            Storage::disk('public')->delete($eski);
        }

        return redirect()->route('admin.category-banners.index')
            ->with('success', $this->label($categoryBanner).' banner\'ı güncellendi.')
            ->with('kb_open', $this->openKey($categoryBanner));
    }

    public function toggle(CategoryBanner $categoryBanner)
    {
        $categoryBanner->update(['is_active' => ! $categoryBanner->is_active]);

        return redirect()->route('admin.category-banners.index')
            ->with('success', $this->label($categoryBanner).' banner\'ı '.($categoryBanner->is_active ? 'yayına alındı.' : 'yayından kaldırıldı.'))
            ->with('kb_open', $this->openKey($categoryBanner));
    }

    public function destroy(CategoryBanner $categoryBanner)
    {
        $etiket = $this->label($categoryBanner);
        $acik = $this->openKey($categoryBanner);

        if ($categoryBanner->image && Storage::disk('public')->exists($categoryBanner->image)) {
            Storage::disk('public')->delete($categoryBanner->image);
        }
        $categoryBanner->delete();

        return redirect()->route('admin.category-banners.index')
            ->with('success', $etiket.' banner\'ı silindi.')
            ->with('kb_open', $acik);
    }

    /** @return array<string, mixed> */
    private function textRules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:220'],
            'caption' => ['nullable', 'string', 'max:120'],
            'darkness' => ['nullable', 'integer', 'min:0', 'max:100'],
            'form_key' => ['nullable', 'string', 'max:40'], // hata/akordeon hedefi, kaydedilmez
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'category_id.unique' => 'Bu kategorinin zaten bir banner\'ı var; onu düzenleyin.',
            'image.required' => 'Banner görseli zorunlu.',
            'image.max' => 'Görsel en fazla 20 MB olabilir.',
            'image.mimes' => 'Görsel JPG, PNG veya WebP olmalı.',
        ];
    }

    /** @return array<string, mixed> */
    private function textValues(array $validated): array
    {
        $bos = fn ($v) => trim((string) $v) === '' ? null : trim((string) $v);

        return [
            'eyebrow' => $bos($validated['eyebrow'] ?? null),
            'title' => $bos($validated['title'] ?? null),
            'subtitle' => $bos($validated['subtitle'] ?? null),
            'caption' => $bos($validated['caption'] ?? null),
            'darkness' => isset($validated['darkness']) ? (int) $validated['darkness'] : CategoryBanner::DEFAULT_DARKNESS,
        ];
    }

    private function label(CategoryBanner $banner): string
    {
        return $banner->category_id ? (string) $banner->category?->name : 'Genel varsayılan';
    }

    /** Kayıttan dönünce hangi akordeon açılsın: "kb-{kategori id}" ya da "kb-genel". */
    private function openKey(CategoryBanner $banner): string
    {
        return $banner->category_id ? 'kb-'.$banner->category_id : 'kb-genel';
    }
}
