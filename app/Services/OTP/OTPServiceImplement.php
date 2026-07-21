<?php

namespace App\Services\OTP;

use App\Enums\OTPTypeEnum;
use App\Mail\ResetPasswordOtpMail;
use App\Mail\VerifyEmailOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class OTPServiceImplement implements OTPService
{
    /**
     * Generate a 6 digit OTP code.
     *
     * In non production environments returns 111111 for easy testing.
     *
     * @return string The generated OTP code
     */
    protected function generateOtp(): string
    {
        if (app()->environment('local', 'development', 'testing', 'staging')) {
            return '111111';
        }

        return (string) mt_rand(100000, 999999);
    }

    /** {@inheritDoc} */
    public function createAndSendOTP(string $type, string $email): void
    {
        $otp = $this->generateOtp();

        Cache::put("{$type}_{$email}", $otp, now()->addMinutes(5));

        $mailable = match ($type) {
            OTPTypeEnum::VERIFY_EMAIL_OTP->name => new VerifyEmailOtpMail($email, $otp),
            OTPTypeEnum::RESET_PASSWORD_OTP->name => new ResetPasswordOtpMail($email, $otp),
            default => null,
        };

        if ($mailable) {
            Mail::to($email)->queue($mailable);
        }
    }

    /** {@inheritDoc} */
    public function verifyOtpCode(string $type, string $email, string $inputOtp): bool
    {
        $cachedOtp = Cache::get("{$type}_{$email}");

        if (! $cachedOtp || $cachedOtp !== $inputOtp) {
            return false;
        }

        Cache::forget("{$type}_{$email}");

        return true;
    }
}
