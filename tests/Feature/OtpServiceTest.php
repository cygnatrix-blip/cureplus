<?php

namespace Tests\Feature;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Services\Auth\OtpService;
use App\Services\Sms\LogSmsProvider;
use RuntimeException;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private const MOBILE = '+919812345678';

    public function test_a_code_is_single_use(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->issue(OtpPurpose::Login, self::MOBILE);

        $this->assertTrue($otp->verify(OtpPurpose::Login, self::MOBILE, $code));
        $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $code));
    }

    public function test_a_code_expires(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->issue(OtpPurpose::Login, self::MOBILE);

        $this->travel(config('cureplus.otp.ttl_minutes') + 1)->minutes();

        $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $code));
    }

    public function test_too_many_wrong_guesses_kill_the_code(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->issue(OtpPurpose::Login, self::MOBILE);
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < config('cureplus.otp.max_attempts'); $i++) {
            $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $wrong));
        }

        $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $code));
    }

    public function test_a_wrong_guess_does_not_change_the_expiry_or_stop_the_right_code(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->issue(OtpPurpose::Login, self::MOBILE);
        $expiry = OtpCode::query()->firstOrFail()->expires_at;
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->travel(1)->minutes();
        $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $wrong));

        $this->assertTrue($expiry->equalTo(OtpCode::query()->firstOrFail()->expires_at));
        $this->assertTrue($otp->verify(OtpPurpose::Login, self::MOBILE, $code));
    }

    public function test_a_resend_retires_the_old_code(): void
    {
        $otp = app(OtpService::class);
        $old = $otp->issue(OtpPurpose::Login, self::MOBILE);
        $new = $otp->issue(OtpPurpose::Login, self::MOBILE);

        if ($old !== $new) {
            $this->assertFalse($otp->verify(OtpPurpose::Login, self::MOBILE, $old));
        }
        $this->assertTrue($otp->verify(OtpPurpose::Login, self::MOBILE, $new));
    }

    public function test_a_code_only_works_for_its_purpose_and_number(): void
    {
        $otp = app(OtpService::class);
        $code = $otp->issue(OtpPurpose::Login, self::MOBILE);

        $this->assertFalse($otp->verify(OtpPurpose::TwoFactor, self::MOBILE, $code));
        $this->assertFalse($otp->verify(OtpPurpose::Login, '+919800000000', $code));
    }

    public function test_neither_the_code_nor_the_number_is_stored_readably(): void
    {
        $code = app(OtpService::class)->issue(OtpPurpose::Login, self::MOBILE);
        $row = OtpCode::query()->firstOrFail();

        $this->assertStringNotContainsString($code, $row->code_hash);
        $this->assertStringNotContainsString('9812345678', $row->identifier_hash);
    }

    public function test_the_log_driver_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        (new LogSmsProvider)->send(self::MOBILE, 'code');
    }
}
