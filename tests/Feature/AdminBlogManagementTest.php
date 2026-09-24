<?php

namespace Tests\Feature;

use App\Jobs\GenerateKnowledgeEmbeddingJob;
use App\Models\KnowledgeChunk;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin blog yönetimi (2026-09-24 yeniden tasarım):
 * - sayfa kenar çubuklu (denetim N1: yetim sayfa),
 * - sayaçlar + filtreler,
 * - taslak yazıya site bağlantısı basılmaz (sitede 404 verirdi),
 * - doğrulama sınırları DB şemasıyla hizalı (image 255, http(s), kategori listesi),
 * - yayın anahtarı hidden 0 ile gider; doğrulama hatasında kaldırılan işaret geri gelmez,
 * - silme bilgi bankası parçasını da temizler, slug başlık değişince sabit kalır.
 */
class AdminBlogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function yazi(array $attrs = []): Post
    {
        return Post::create(array_merge([
            'title'        => 'Kapadokya Turu',
            'content'      => 'Balon, vadi, yeraltı şehri.',
            'category'     => 'Tur Haberleri',
            'is_published' => true,
        ], $attrs));
    }

    public function test_misafir_girise_yonlenir_ziyaretci_giremez(): void
    {
        $this->get(route('admin.blog.index'))->assertRedirect(route('login'));

        $ziyaretci = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $this->actingAs($ziyaretci)->get(route('admin.blog.index'))->assertForbidden();
    }

    public function test_liste_kenar_cubugu_ve_sayaclarla_gelir(): void
    {
        $this->yazi();
        $this->yazi(['title' => 'Taslak Yazı', 'is_published' => false]);

        $r = $this->actingAs($this->admin)->get(route('admin.blog.index'))->assertOk();
        $r->assertSee('panel-sidebar-module', false); // N1: yetim sayfa değil
        $r->assertViewHas('sayac', ['toplam' => 2, 'yayinda' => 1, 'taslak' => 1]);
        $r->assertSee('Kapadokya Turu')->assertSee('Taslak Yazı');
    }

    public function test_taslak_yazida_site_baglantisi_yok_yayindakinde_var(): void
    {
        $yayinda = $this->yazi();
        $taslak = $this->yazi(['title' => 'Gizli Taslak', 'is_published' => false]);

        $r = $this->actingAs($this->admin)->get(route('admin.blog.index'))->assertOk();
        $r->assertSee(route('blog.show', $yayinda), false);
        $r->assertDontSee(route('blog.show', $taslak), false);
        $r->assertSee(route('admin.blog.edit', $taslak), false);
    }

    public function test_filtreler_calisir(): void
    {
        $this->yazi(['title' => 'Roma Rehberi', 'category' => 'Rehber']);
        $this->yazi(['title' => 'Paris Taslağı', 'category' => 'Destinasyon', 'is_published' => false]);

        $this->actingAs($this->admin)->get(route('admin.blog.index', ['durum' => 'taslak']))
            ->assertOk()->assertSee('Paris Taslağı')->assertDontSee('Roma Rehberi');

        $this->actingAs($this->admin)->get(route('admin.blog.index', ['kategori' => 'Rehber']))
            ->assertOk()->assertSee('Roma Rehberi')->assertDontSee('Paris Taslağı');

        $this->actingAs($this->admin)->get(route('admin.blog.index', ['q' => 'roma']))
            ->assertOk()->assertSee('Roma Rehberi')->assertDontSee('Paris Taslağı');

        // Bilinmeyen değerler sessizce yok sayılır, 500 vermez
        $this->actingAs($this->admin)->get(route('admin.blog.index', ['durum' => 'x', 'kategori' => 'y', 'q' => ['dizi']]))
            ->assertOk()->assertSee('Roma Rehberi')->assertSee('Paris Taslağı');
    }

    public function test_yeni_yazi_slug_ve_yayin_tarihiyle_olusur(): void
    {
        $this->actingAs($this->admin)->post(route('admin.blog.store'), [
            'title'            => 'Şile Plajları',
            'content'          => 'Metin',
            'category'         => 'Rehber',
            'excerpt'          => 'Özet',
            'meta_description' => 'Meta',
            'image'            => 'https://example.com/kapak.jpg',
            'is_published'     => '1',
        ])->assertRedirect(route('admin.blog.index'))->assertSessionHas('success', 'Yazı oluşturuldu ve yayınlandı.');

        $post = Post::firstOrFail();
        $this->assertStringStartsWith('sile-plajlari-', $post->slug);
        $this->assertTrue($post->is_published);
        $this->assertNotNull($post->published_at);
        $this->assertSame(1, KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->count());
        Queue::assertPushed(GenerateKnowledgeEmbeddingJob::class);
    }

    public function test_taslak_kaydi_bilgi_bankasina_girmez(): void
    {
        $this->actingAs($this->admin)->post(route('admin.blog.store'), [
            'title' => 'Taslak', 'content' => 'x', 'category' => 'Rehber', 'is_published' => '0',
        ])->assertSessionHas('success', 'Yazı taslak olarak kaydedildi.');

        $post = Post::firstOrFail();
        $this->assertFalse($post->is_published);
        $this->assertNull($post->published_at);
        $this->assertSame(0, KnowledgeChunk::count());
    }

    public function test_dogrulama_sinirlari(): void
    {
        $this->actingAs($this->admin)->from(route('admin.blog.create'))->post(route('admin.blog.store'), [
            'title'            => '',
            'content'          => '',
            'category'         => 'Olmayan Kategori',
            'image'            => 'javascript:alert(1)',
            'meta_description' => str_repeat('a', 161),
        ])->assertRedirect(route('admin.blog.create'))
          ->assertSessionHasErrors(['title', 'content', 'category', 'image', 'meta_description']);

        // image kolonu string(255): uzun adres DB'ye düşmeden reddedilir (eskiden 500)
        $this->actingAs($this->admin)->post(route('admin.blog.store'), [
            'title' => 'X', 'content' => 'Y', 'category' => 'Rehber',
            'image' => 'https://example.com/' . str_repeat('a', 300),
        ])->assertSessionHasErrors('image');

        $this->assertSame(0, Post::count());
    }

    public function test_eski_kaydin_listede_olmayan_kategorisi_dogrulamaya_takilmaz(): void
    {
        $post = $this->yazi(['category' => 'Eski Kategori']);

        $this->actingAs($this->admin)->get(route('admin.blog.edit', $post))->assertOk()
            ->assertSee('<option value="Eski Kategori" selected>', false);

        $this->actingAs($this->admin)->put(route('admin.blog.update', $post), [
            'title' => $post->title, 'content' => $post->content, 'category' => 'Eski Kategori', 'is_published' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_yayin_isareti_kaldirilinca_yazi_taslaga_doner(): void
    {
        $post = $this->yazi();
        $this->assertSame(1, KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->count());

        $this->actingAs($this->admin)->put(route('admin.blog.update', $post), [
            'title' => $post->title, 'content' => $post->content, 'category' => $post->category, 'is_published' => '0',
        ])->assertRedirect(route('admin.blog.index'))->assertSessionHas('success', 'Yazı güncellendi ve taslağa alındı.');

        $this->assertFalse($post->fresh()->is_published);
        $this->assertSame(0, KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->count());
    }

    public function test_yayin_anahtari_hidden_0_ile_gider_ve_hatada_kaldirilan_isaret_geri_gelmez(): void
    {
        $post = $this->yazi(); // yayında

        $r = $this->actingAs($this->admin)->get(route('admin.blog.edit', $post))->assertOk();
        $r->assertSee('<input type="hidden" name="is_published" value="0">', false);
        $r->assertSee('id="is_published" value="1" checked', false);

        // Kullanıcı işareti kaldırıp başlığı boş bıraktı: hata sayfasında işaret KAPALI kalmalı
        $r = $this->actingAs($this->admin)->from(route('admin.blog.edit', $post))->followingRedirects()
            ->put(route('admin.blog.update', $post), [
                'title' => '', 'content' => 'x', 'category' => 'Rehber', 'is_published' => '0',
            ])->assertOk();
        $r->assertSee('Başlık zorunludur.');
        $r->assertDontSee('id="is_published" value="1" checked', false);
        $this->assertTrue($post->fresh()->is_published, 'Doğrulama hatası kaydı değiştirmemeli');
    }

    public function test_slug_baslik_degisince_sabit_kalir(): void
    {
        $post = $this->yazi();
        $eskiSlug = $post->slug;

        $this->actingAs($this->admin)->put(route('admin.blog.update', $post), [
            'title' => 'Yepyeni Başlık', 'content' => 'x', 'category' => 'Rehber', 'is_published' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($eskiSlug, $post->fresh()->slug);
        $this->assertSame('Yepyeni Başlık', $post->fresh()->title);
    }

    public function test_silme_bilgi_bankasi_parcasini_da_siler(): void
    {
        $post = $this->yazi();
        $this->assertSame(1, KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->count());

        $this->actingAs($this->admin)->delete(route('admin.blog.destroy', $post))
            ->assertRedirect(route('admin.blog.index'))->assertSessionHas('success');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertSame(0, KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->count());
    }

    public function test_form_sayfalari_kenar_cubuklu_ve_sil_formu_ana_formun_disinda(): void
    {
        $post = $this->yazi();

        $this->actingAs($this->admin)->get(route('admin.blog.create'))->assertOk()
            ->assertSee('panel-sidebar-module', false)
            ->assertDontSee('yaziSilForm', false);

        $html = $this->actingAs($this->admin)->get(route('admin.blog.edit', $post))->assertOk()
            ->assertSee('panel-sidebar-module', false)
            ->assertSee('form="yaziSilForm"', false)
            ->getContent();

        // Sil formu ana </form>'dan SONRA başlar (iç içe form yok)
        $anaKapanis = strpos($html, '</form>', strpos($html, 'id="blogForm"'));
        $silBaslangic = strpos($html, 'id="yaziSilForm"');
        $this->assertNotFalse($anaKapanis);
        $this->assertNotFalse($silBaslangic);
        $this->assertGreaterThan($anaKapanis, $silBaslangic);
    }
}
