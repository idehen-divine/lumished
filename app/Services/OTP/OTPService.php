<?php

namespace App\Services\OTP;

interface OTPService
{
    /**
     * Create an OTP code and send it to the given email.
     *
     * Generates a 6 digit OTP, caches it for 5 minutes under the key
     * "{type}_{email}", and queues the appropriate mailable based on
     * the OTP type.
     *
     * @param  string  $type  The OTP type (VERIFY_EMAIL_OTP, RESET_PASSWORD_OTP, etc.)
     * @param  string  $email  The recipient email address
     */
    public function createAndSendOTP(string $type, string $email): void;

    /**
     * Verify an OTP code for the given type and email.
     *
     * Compares the provided code against the cached value. If valid,
     * the cached OTP is removed (one time use).
     *
     * @param  string  $type  The OTP type
     * @param  string  $email  The email address
     * @param  string  $inputOtp  The OTP code to verify
     * @return bool True if the OTP is valid, false otherwise
     */
    public function verifyOtpCode(string $type, string $email, string $inputOtp): bool;
}
