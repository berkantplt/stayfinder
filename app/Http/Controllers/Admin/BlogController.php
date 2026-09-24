<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BlogController extends Controller
{
    /** @var list<string>|null */
    private ?array $kategoriOnbellek = null;

    public function index(Request $request)
    {
        $q        = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $durum    = in_array($request->query('durum'), ['yayinda', 'taslak'], true) ? $request->query('durum') : null;
        $kategori = in_array($request->query('kategori'), $this->kategoriler(), true) ? $request->query('kategori') : null;

        $posts = Post::query()
            ->when($q !== '', function ($sorgu) use ($q) {
                $desen = '%' . addcslashes($q, '%_\\') . '%';
                $sorgu->where(fn ($w) => $w->where('title', 'like', $desen)->orWhere('excerpt', 'like', $desen));
            })
            ->when($durum === 'yayinda', fn ($sorgu) => $sorgu->where('is_published', true))
            ->when($durum === 'taslak', fn ($sorgu) => $sorgu->where('is_published', false))
            ->when($kategori !== null, fn ($sorgu) => $sorgu->where('category', $kategori))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $toplam  = Post::count();
        $yayinda = Post::where('is_published', true)->count();

        return view('admin.blog.index', [
            'posts'       => $posts,
            'sayac'       => ['toplam' => $toplam, 'yayinda' => $yayinda, 'taslak' => $toplam - $yayinda],
            'q'           => $q,
            'durum'       => $durum,
            'kategori'    => $kategori,
            'kategoriler' => $this->kategoriler(),
            'filtreli'    => $q !== '' || $durum !== null || $kategori !== null,
        ]);
    }

    public function create()
    {
        return view('admin.blog.form', ['kategoriler' => $this->kategoriler()]);
    }

    public function store(Request $request)
    {
        $post = Post::create($this->dogrula($request));

        return redirect()->route('admin.blog.index')
            ->with('success', $post->is_published ? 'Yazı oluşturuldu ve yayınlandı.' : 'Yazı taslak olarak kaydedildi.');
    }

    public function edit(Post $post)
    {
        return view('admin.blog.form', ['post' => $post, 'kategoriler' => $this->kategoriler()]);
    }

    public function update(Request $request, Post $post)
    {
        $post->update($this->dogrula($request));

        return redirect()->route('admin.blog.index')
            ->with('success', $post->is_published ? 'Yazı güncellendi.' : 'Yazı güncellendi ve taslağa alındı.');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Yazı silindi.');
    }

    /**
     * store/update tek kural seti. Sınırlar DB şemasıyla hizalı: image string(255)
     * (eskiden max yoktu, uzun URL 500 veriyordu), category yalnız bilinen liste,
     * image yalnız http(s) (Laravel'in varsayılan url kuralı javascript:/data: dahil
     * yüzlerce şemayı kabul ediyor).
     *
     * @return array<string, mixed>
     */
    private function dogrula(Request $request): array
    {
        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'content'          => ['required', 'string'],
            'excerpt'          => ['nullable', 'string', 'max:500'],
            'image'            => ['nullable', 'string', 'max:255', 'url:http,https'],
            'category'         => ['required', 'string', Rule::in($this->kategoriler())],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_published'     => ['nullable', 'boolean'],
        ], [
            'title.required'       => 'Başlık zorunludur.',
            'title.max'            => 'Başlık en fazla 255 karakter olabilir.',
            'content.required'     => 'İçerik boş bırakılamaz.',
            'excerpt.max'          => 'Kısa özet en fazla 500 karakter olabilir.',
            'image.max'            => 'Kapak görseli adresi en fazla 255 karakter olabilir.',
            'image.url'            => 'Kapak görseli http(s) ile başlayan geçerli bir adres olmalı.',
            'category.required'    => 'Kategori seçin.',
            'category.in'          => 'Geçersiz kategori.',
            'meta_description.max' => 'Meta açıklama en fazla 160 karakter olabilir; Google fazlasını keser.',
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        return $validated;
    }

    /**
     * Sabit liste + DB'deki eski/farklı kategoriler (grandfathering: eski bir yazı
     * listede olmayan kategorisiyle düzenlenebilsin, doğrulamaya takılmasın).
     *
     * @return list<string>
     */
    private function kategoriler(): array
    {
        return $this->kategoriOnbellek ??= array_values(array_unique(array_merge(
            Post::CATEGORIES,
            Post::query()->distinct()->orderBy('category')->pluck('category')->all(),
        )));
    }
}
