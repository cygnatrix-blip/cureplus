<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Admin-editable settings. Values set here beat .env, so keys can be rotated without
 * shell access. Each maps to the config value the rest of the app already reads.
 */
class PlatformSettings
{
    private const CACHE_KEY = 'platform-settings.v1';

    public const SECRET_KEYS = ['mail.password', 'sms.token', 'whatsapp.token', 'push.private_key', 'razorpay.key_secret', 'razorpay.webhook_secret', 'google.client_secret'];

    private const CONFIG_MAP = [
        'mail.host' => 'mail.mailers.smtp.host',
        'mail.port' => 'mail.mailers.smtp.port',
        'mail.username' => 'mail.mailers.smtp.username',
        'mail.password' => 'mail.mailers.smtp.password',
        'mail.from_address' => 'mail.from.address',
        'mail.from_name' => 'mail.from.name',
        'sms.driver' => 'cureplus.sms.driver',
        'sms.url' => 'cureplus.sms.http.url',
        'sms.token' => 'cureplus.sms.http.token',
        'sms.sender' => 'cureplus.sms.http.sender',
        'whatsapp.token' => 'messages.whatsapp.token',
        'whatsapp.phone_number_id' => 'messages.whatsapp.phone_number_id',
        'push.subject' => 'messages.push.subject',
        'push.public_key' => 'messages.push.public_key',
        'push.private_key' => 'messages.push.private_key',
        'razorpay.key_id' => 'plans.razorpay.key_id',
        'razorpay.key_secret' => 'plans.razorpay.key_secret',
        'razorpay.webhook_secret' => 'plans.razorpay.webhook_secret',
        'google.client_id' => 'services.google.client_id',
        'google.client_secret' => 'services.google.client_secret',
        'organizations.require_approval' => 'cureplus.organizations.require_approval',
    ];

    /**
     * Runs on every request, so it is cached. It must never take the site down:
     * before migrations have run, or if the database is away, .env values simply apply.
     */
    public function applyToConfig(): void
    {
        try {
            // Fetched first and plucked in PHP, so the encrypted cast runs on each value.
            // @phpstan-ignore larastan.noUnnecessaryCollectionCall
            $values = Cache::rememberForever(self::CACHE_KEY, fn () => PlatformSetting::query()->get()->pluck('value', 'setting_key')->all());
        } catch (Throwable) {
            return;
        }

        foreach (self::CONFIG_MAP as $setting => $configKey) {
            if (array_key_exists($setting, $values)) {
                $value = $values[$setting];
                config([$configKey => $setting === 'organizations.require_approval' ? (bool) $value : $value]);
            }
        }

        // Saved details must take effect: otherwise a site in log mode keeps writing email to a file.
        if (! empty($values['mail.host']) && ! empty($values['mail.username'])) {
            config(['mail.default' => 'smtp']);
        }

        if (! empty($values['security.two_factor_all_staff'])) {
            config(['cureplus.security.two_factor_roles' => array_keys(config('rbac.roles'))]);
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return PlatformSetting::query()->find($key)->value ?? $default;
    }

    /**
     * Blank secrets are skipped: saved secrets are not sent back to the form,
     * so an empty box means "keep what is saved".
     *
     * @param  array<string, string|null>  $values
     */
    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (blank($value)) {
                if (! in_array($key, self::SECRET_KEYS, true)) {
                    PlatformSetting::query()->whereKey($key)->delete();
                }

                continue;
            }

            PlatformSetting::query()->updateOrCreate(['setting_key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->applyToConfig();
    }

    public function masked(string $key): ?string
    {
        $value = $this->get($key);

        return $value === null ? null : '••••'.substr($value, -4);
    }
}
