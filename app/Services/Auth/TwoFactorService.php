<?php

namespace App\Services\Auth;

use App\Models\PlatformAdmin;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrSvg(User|PlatformAdmin $account, #[\SensitiveParameter] string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('app.name'), $account->email ?? (string) $account->mobile, $secret);

        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($url);
    }

    public function verifyTotp(User|PlatformAdmin $account, #[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): bool
    {
        if (! $this->google2fa->verifyKey($secret, $code, 1)) {
            return false;
        }

        // A code stays valid for about a minute. Remembering it stops an observed
        // code from being replayed in that window.
        return Cache::add('2fa.used.'.class_basename($account).'.'.$account->getKey().'.'.$code, true, 180);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, 8)
        );
    }

    public function consumeRecoveryCode(User|PlatformAdmin $account, #[\SensitiveParameter] string $code): bool
    {
        $codes = $account->two_factor_recovery_codes ?? [];
        $index = array_search(Str::lower(trim($code)), $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $account->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

        return true;
    }

    public function enable(User|PlatformAdmin $account, string $method, #[\SensitiveParameter] ?string $secret): void
    {
        $account->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $this->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->saveQuietly();
    }
}
