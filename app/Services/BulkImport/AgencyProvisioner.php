<?php

namespace App\Services\BulkImport;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryLicensing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Toplu içe aktarım için acenta hesabı kurar: acenta kaydı (onaylı, legacy
 * erişimi YOK), panel kullanıcısı ve kategori başına manuel abonelik.
 *
 * Abonelik kalıbı Admin\AdminController::grantCategory ile birebir: denetim izi
 * için 0 TL manuel sipariş + kalem + aktif abonelik. Tek fark BİLEREK: acenta
 * kullanıcısına bildirim/e-posta GÖNDERİLMEZ (panel adresleri uydurma, gerçek
 * acentaya mail gitmesin) ve ekstra tur hakkı doğrudan yazılır (10 tur sığsın).
 *
 * Yeniden çalıştırılabilir: var olan acenta/kullanıcı/aktif abonelik dokunulmaz,
 * yalnız eksikler tamamlanır.
 */
class AgencyProvisioner
{
    public const DEFAULT_MONTHS = 12;

    public const DEFAULT_EXTRA_TOUR_SLOTS = 8;

    /**
     * @param  array<string, mixed>  $definition  {slug, name, website_url, phone?, email?, panel_email, panel_name?, description?, address?, categories: string[], months?, extra_tour_slots?}
     * @return array{agency: ?Agency, agency_status: string, user_status: string, password: ?string, subscriptions: array<string, string>, notes: string[]}
     *
     * @throws BulkImportException
     */
    public function provision(array $definition, string $batch, ?string $password = null, bool $dry = false): array
    {
        $def = $this->validateDefinition($definition);
        $categories = $this->resolveCategories($def['categories']);

        $existingAgency = Agency::withTrashed()->where('slug', $def['slug'])->first();
        if ($existingAgency?->trashed()) {
            throw new BulkImportException("'{$def['slug']}' acentası arşivde — önce admin panelinden geri alın.");
        }

        // Panel e-postası başka bir kullanıcıya/acentaya aitse sessizce devralma:
        // yeniden koşumda yalnız BU acentaya bağlı kullanıcı "mevcut" sayılır.
        $existingUser = User::where('email', $def['panel_email'])->first();
        if ($existingUser && (! $existingAgency || (int) $existingUser->agency_id !== (int) $existingAgency->id)) {
            throw new BulkImportException("'{$def['panel_email']}' başka bir hesaba bağlı (user #{$existingUser->id}) — panel e-postasını değiştirin.");
        }

        $notes = [];
        $generatedPassword = null;

        if ($dry) {
            $subs = [];
            foreach ($categories as $slug => $category) {
                $subs[$slug] = $existingAgency && $this->activeSubscription($existingAgency, $category)
                    ? 'mevcut (dokunulmayacak)'
                    : "yaratılacak ({$def['months']} ay, +{$def['extra_tour_slots']} hak)";
            }

            return [
                'agency' => $existingAgency,
                'agency_status' => $existingAgency ? 'mevcut' : 'yaratılacak',
                'user_status' => $existingUser ? 'mevcut' : 'yaratılacak',
                'password' => null,
                'subscriptions' => $subs,
                'notes' => $notes,
            ];
        }

        $result = DB::transaction(function () use ($def, $categories, $batch, $password, $existingAgency, $existingUser, &$generatedPassword) {
            $agency = $existingAgency;
            $agencyStatus = 'mevcut';
            if (! $agency) {
                $agency = Agency::create([
                    'name' => $def['name'],
                    'slug' => $def['slug'],
                    'website_url' => $def['website_url'],
                    'phone' => $def['phone'],
                    'email' => $def['email'],
                    'address' => $def['address'],
                    'description' => $def['description'],
                    'is_active' => true,
                    'approval_status' => Agency::STATUS_APPROVED,
                    'approved_at' => now(),
                    'legacy_category_access' => false,
                    'import_batch' => $batch,
                ]);
                $agencyStatus = 'yaratıldı';
            } elseif ($agency->import_batch === null) {
                // Elle açılmış acentaya sonradan toplu tur yüklenmişse izi kalsın
                $agency->forceFill(['import_batch' => $batch])->save();
            }

            $user = $existingUser;
            $userStatus = 'mevcut';
            if (! $user) {
                $plain = $password ?? Str::password(14, symbols: false);
                $generatedPassword = $password === null ? $plain : null;
                $user = User::create([
                    'name' => $def['panel_name'],
                    'email' => $def['panel_email'],
                    'password' => $plain,
                    'role' => 'agency',
                    'agency_id' => $agency->id,
                ]);
                // Doğrulama maili atılmasın: adres uydurma, panel direkt açılsın.
                $user->forceFill(['email_verified_at' => now(), 'password_set_at' => now()])->save();
                $userStatus = 'yaratıldı';
            }

            $subs = [];
            foreach ($categories as $slug => $category) {
                $subs[$slug] = $this->grantSubscription($agency, $category, $def['months'], $def['extra_tour_slots']);
            }

            return [
                'agency' => $agency,
                'agency_status' => $agencyStatus,
                'user_status' => $userStatus,
                'subscriptions' => $subs,
            ];
        });

        $result['password'] = $generatedPassword;
        $result['notes'] = $notes;

        return $result;
    }

    /**
     * @return array{slug: string, name: string, website_url: string, phone: ?string, email: ?string, panel_email: string, panel_name: string, description: ?string, address: ?string, categories: string[], months: int, extra_tour_slots: int}
     */
    private function validateDefinition(array $def): array
    {
        foreach (['slug', 'name', 'website_url', 'panel_email'] as $key) {
            if (trim((string) ($def[$key] ?? '')) === '') {
                throw new BulkImportException("Acenta tanımında '{$key}' eksik.");
            }
        }
        $slug = Str::slug((string) $def['slug']);
        if ($slug !== $def['slug']) {
            throw new BulkImportException("Acenta slug'ı URL-uyumlu olmalı: '{$def['slug']}' → '{$slug}'.");
        }
        if (! filter_var($def['panel_email'], FILTER_VALIDATE_EMAIL)) {
            throw new BulkImportException("Geçersiz panel e-postası: '{$def['panel_email']}'.");
        }
        if (! filter_var($def['website_url'], FILTER_VALIDATE_URL)) {
            throw new BulkImportException("Geçersiz web adresi: '{$def['website_url']}'.");
        }
        $categories = array_values(array_unique(array_map('strval', (array) ($def['categories'] ?? []))));
        if ($categories === []) {
            throw new BulkImportException("'{$slug}' için en az bir kategori slug'ı gerekli.");
        }
        $months = (int) ($def['months'] ?? self::DEFAULT_MONTHS);
        if ($months < 1 || $months > 24) {
            throw new BulkImportException("'{$slug}': abonelik süresi 1-24 ay olmalı.");
        }
        $slots = (int) ($def['extra_tour_slots'] ?? self::DEFAULT_EXTRA_TOUR_SLOTS);
        if ($slots < 0 || $slots > 500) {
            throw new BulkImportException("'{$slug}': ekstra tur hakkı 0-500 arası olmalı.");
        }

        return [
            'slug' => $slug,
            'name' => trim((string) $def['name']),
            'website_url' => trim((string) $def['website_url']),
            'phone' => isset($def['phone']) ? trim((string) $def['phone']) : null,
            'email' => isset($def['email']) ? trim((string) $def['email']) : null,
            'panel_email' => strtolower(trim((string) $def['panel_email'])),
            'panel_name' => trim((string) ($def['panel_name'] ?? ($def['name'].' Yönetici'))),
            'description' => isset($def['description']) ? trim((string) $def['description']) : null,
            'address' => isset($def['address']) ? trim((string) $def['address']) : null,
            'categories' => $categories,
            'months' => $months,
            'extra_tour_slots' => $slots,
        ];
    }

    /**
     * Yalnız AKTİF ALT kategoriler (üst kategori satılmaz/verilmez — C19).
     *
     * @param  string[]  $slugs
     * @return array<string, Category>
     */
    private function resolveCategories(array $slugs): array
    {
        $found = Category::query()->whereIn('slug', $slugs)->get()->keyBy('slug');
        $missing = [];
        foreach ($slugs as $slug) {
            $category = $found->get($slug);
            if (! $category) {
                $missing[] = "{$slug} (yok)";
            } elseif ($category->parent_id === null) {
                $missing[] = "{$slug} (üst kategori — tur alamaz)";
            } elseif (! $category->is_active) {
                $missing[] = "{$slug} (pasif)";
            }
        }
        if ($missing !== []) {
            throw new BulkImportException('Kategori sorunu: '.implode(', ', $missing));
        }

        $result = [];
        foreach ($slugs as $slug) {
            $result[$slug] = $found->get($slug);
        }

        return $result;
    }

    private function activeSubscription(Agency $agency, Category $category): ?AgencyCategorySubscription
    {
        $subscription = AgencyCategorySubscription::query()
            ->where('agency_id', $agency->id)
            ->where('category_id', $category->id)
            ->first();

        return $subscription && $subscription->is_active ? $subscription : null;
    }

    /** Admin manuel grant kalıbı (0 TL sipariş + abonelik); aktif abonelik dokunulmaz. */
    private function grantSubscription(Agency $agency, Category $category, int $months, int $extraSlots): string
    {
        $subscription = AgencyCategorySubscription::query()
            ->where('agency_id', $agency->id)
            ->where('category_id', $category->id)
            ->lockForUpdate()
            ->first();

        if ($subscription && $subscription->is_active) {
            return 'mevcut (dokunulmadı)';
        }

        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => $this->generateManualOrderNumber(),
            'billing_cycle' => 'monthly',
            'subtotal' => 0,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PAID,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_MANUAL,
            'buyer_type' => AgencyCategoryOrder::BUYER_INDIVIDUAL,
            'purchased_at' => now(),
            'paid_at' => now(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'unit_price' => 0,
            'billing_cycle' => 'monthly',
        ]);

        $today = now()->startOfDay();
        $attributes = [
            'last_order_id' => $order->id,
            'monthly_price' => (float) $category->monthly_price,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => $today,
            'expires_at' => $today->copy()->addMonths($months),
            'renewal_reminder_sent_at' => null,
        ];
        if (CategoryLicensing::slotSchemaReady()) {
            $attributes['extra_tour_slots'] = $extraSlots;
        }
        if (CategoryLicensing::autoRenewSchemaReady()) {
            $attributes['auto_renew'] = true;
            $attributes['cancelled_at'] = null;
            $attributes['next_extra_tour_slots'] = null;
        }

        if ($subscription) {
            $subscription->update($attributes);

            return "yenilendi ({$months} ay, +{$extraSlots} hak)";
        }

        AgencyCategorySubscription::create(array_merge($attributes, [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
        ]));

        return "yaratıldı ({$months} ay, +{$extraSlots} hak)";
    }

    private function generateManualOrderNumber(): string
    {
        do {
            $number = 'KYM-MAN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (AgencyCategoryOrder::where('order_number', $number)->exists());

        return $number;
    }
}
