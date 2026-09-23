<?php

namespace App\Services\Auth;

use App\Models\SocialAccount;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Sağlayıcıdan dönen ham kullanıcıyı tek bir şekle indirger.
 *
 * Asıl işi email_verified'ı doğru okumak: hesap eşleştirme kararı buna bakıyor
 * (bkz. SocialAccountResolver). Google bool, Apple ise kimlik jetonunda çoğu kez
 * "true" METNİ döndürür — düz === true karşılaştırması Apple'ı hep doğrulanmamış
 * sayar ve her Apple kullanıcısını çakışma ekranına düşürürdü. Alan hiç yoksa
 * cevap daima false: doğrulanmamış adresle mevcut hesaba bağlanılmaz.
 */
class SocialUserData
{
    /**
     * @return array{provider_user_id: string, name: ?string, email: ?string, email_verified: bool, avatar: ?string}
     */
    public function normalize(string $provider, SocialiteUser $socialite): array
    {
        $raw = $socialite->getRaw() ?: [];

        $email = $socialite->getEmail();
        $email = is_string($email) ? mb_strtolower(trim($email)) : null;

        return [
            'provider_user_id' => (string) $socialite->getId(),
            'name' => $this->cleanName($socialite->getName()),
            'email' => $email !== '' ? $email : null,
            'email_verified' => $this->emailVerified($provider, $raw),
            'avatar' => $this->cleanAvatar($socialite->getAvatar()),
        ];
    }

    /** @param array<string, mixed> $raw */
    private function emailVerified(string $provider, array $raw): bool
    {
        $claim = $raw['email_verified'] ?? null;

        if ($claim === null) {
            return false;
        }

        // Apple "true" metni de gönderebilir; Google bool gönderir.
        $verified = filter_var($claim, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($verified !== true) {
            return false;
        }

        // Apple'ın gizli aktarma adresi (…@privaterelay.appleid.com) kullanıcıya
        // özeldir, başka birinin gerçek adresiyle çakışmaz; doğrulanmış sayılır.
        return $provider === SocialAccount::PROVIDER_GOOGLE
            || $provider === SocialAccount::PROVIDER_APPLE;
    }

    private function cleanName(?string $name): ?string
    {
        $name = trim((string) $name);

        return $name !== '' ? mb_substr($name, 0, 255) : null;
    }

    /**
     * Sağlayıcının avatar adresi olduğu gibi saklanmaz: yalnız https ve makul
     * uzunlukta olanlar. (users.avatar'a YAZILMAZ — orada yerel dosya yolu var,
     * bkz. ProfileController; sosyal avatar ayrı kolonda durur.)
     */
    private function cleanAvatar(?string $avatar): ?string
    {
        $avatar = trim((string) $avatar);

        if ($avatar === '' || ! str_starts_with($avatar, 'https://') || mb_strlen($avatar) > 1024) {
            return null;
        }

        return $avatar;
    }
}
