<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpDeliveryException;
use App\Models\OtpCode;
use App\Notifications\OtpCodeMail;
use App\Support\Mobile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OtpService
{
    /**
     * Sent inside the request, not through the queue: the queue only runs once a
     * minute from cron, and a code that arrives a minute late looks broken.
     *
     * @throws OtpDeliveryException
     */
    public function sendToEmail(OtpPurpose $purpose, string $email): void
    {
        $code = $this->issue($purpose, $email);

        try {
            Notification::route('mail', $email)->notifyNow(new OtpCodeMail($code));
        } catch (Throwable $exception) {
            report($exception);

            throw new OtpDeliveryException('The code could not be emailed.');
        }
    }

    public function issue(OtpPurpose $purpose, string $identifier): string
    {
        $identifierHash = $this->identifierHash($identifier);
        $length = config('cureplus.otp.length');
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        // Only the newest code works, so asking for a resend retires the older ones.
        OtpCode::query()
            ->where('purpose', $purpose->value)
            ->where('identifier_hash', $identifierHash)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        OtpCode::query()->create([
            'purpose' => $purpose->value,
            'identifier_hash' => $identifierHash,
            'code_hash' => $this->codeHash($purpose, $identifierHash, $code),
            'expires_at' => now()->addMinutes(config('cureplus.otp.ttl_minutes')),
        ]);

        return $code;
    }

    public function verify(OtpPurpose $purpose, string $identifier, #[\SensitiveParameter] string $code): bool
    {
        $identifierHash = $this->identifierHash($identifier);

        // The row lock stops parallel guesses from sharing one attempt count,
        // and stops one code from being consumed twice.
        return DB::transaction(function () use ($purpose, $identifierHash, $code): bool {
            $otp = OtpCode::query()
                ->where('purpose', $purpose->value)
                ->where('identifier_hash', $identifierHash)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->attempts >= config('cureplus.otp.max_attempts')) {
                return false;
            }

            if (! hash_equals($otp->code_hash, $this->codeHash($purpose, $identifierHash, $code))) {
                $otp->increment('attempts');

                return false;
            }

            $otp->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    private function identifierHash(string $identifier): string
    {
        $identifier = Mobile::normalize($identifier) ?? mb_strtolower($identifier);

        return hash_hmac('sha256', $identifier, (string) config('app.key'));
    }

    private function codeHash(OtpPurpose $purpose, string $identifierHash, #[\SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', "{$purpose->value}|{$identifierHash}|{$code}", (string) config('app.key'));
    }
}
