<?php

namespace App\Services\Auth;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

interface AuthService extends BaseService
{
    /**
     * Register a new customer account.
     *
     * Creates a new user with the provided details, assigns the CUSTOMER role,
     * generates an authentication token, and returns the user data with roles
     * and permissions.
     *
     * @param  array  $data  The registration data (first_name, last_name, email, password)
     * @return ServiceApi The service API response with user, token, roles, and permissions
     */
    public function register(array $data): ServiceApi;

    /**
     * Log in a user with the provided credentials.
     *
     * Validates email and password, enforces lockout after 5 failed attempts,
     * revokes all previous tokens for single-device enforcement, and returns
     * a new authentication token with user data, roles, and permissions.
     *
     * @param  array  $credentials  The user's login credentials (email and password)
     * @return ServiceApi The service API response containing the login result
     */
    public function login(array $credentials): ServiceApi;

    /**
     * Log out the authenticated user.
     *
     * Revokes the current access token to invalidate the session.
     *
     * @param  mixed  $user  The authenticated user instance
     * @return ServiceApi The service API response indicating logout success
     */
    public function logout(mixed $user): ServiceApi;

    /**
     * Initiate a password reset by sending an OTP to the user's email.
     *
     * Generates a pending token, caches it, and sends a reset OTP via email.
     * Returns a pending token that is needed for the next step.
     *
     * @param  array  $data  The data containing the user's email
     * @return ServiceApi The service API response with a pending token
     */
    public function forgotPassword(array $data): ServiceApi;

    /**
     * Verify the password reset OTP and return a reset token.
     *
     * Validates the OTP using the pending token, then returns a short-lived
     * reset token that can be used to actually change the password.
     *
     * @param  array  $data  The data containing the OTP and pending token
     * @return ServiceApi The service API response with a reset token
     */
    public function verifyPasswordOtp(array $data): ServiceApi;

    /**
     * Reset the user's password using a verified reset token.
     *
     * Consumes the reset token and updates the user's password.
     * All existing tokens are revoked.
     *
     * @param  array  $data  The data containing the reset token and new password
     * @return ServiceApi The service API response indicating password reset success
     */
    public function resetPassword(array $data): ServiceApi;

    /**
     * Verify the authenticated user's email with an OTP.
     *
     * Validates the OTP and marks the user's email as verified.
     *
     * @param  array  $data  The data containing the OTP code
     * @return ServiceApi The service API response indicating email verification status
     */
    public function verifyEmail(array $data): ServiceApi;

    /**
     * Resend the email verification OTP to the authenticated user.
     *
     * @return ServiceApi The service API response indicating the OTP was sent
     */
    public function resendVerificationEmail(): ServiceApi;

    /**
     * Update the authenticated user's email address.
     *
     * Changes the email and marks it as unverified. A verification OTP
     * is sent to the new email address.
     *
     * @param  array  $data  The data containing the new email address
     * @return ServiceApi The service API response indicating the email was updated
     */
    public function updateEmail(array $data): ServiceApi;

    /**
     * Initiate a password change for the authenticated user.
     *
     * Sends a verification OTP to the user's email. The OTP must be verified
     * before the password can be changed.
     *
     * @return ServiceApi The service API response with a pending token
     */
    public function initiatePasswordChange(): ServiceApi;

    /**
     * Verify the password change OTP and return a reset token.
     *
     * @param  array  $data  The data containing the OTP and pending token
     * @return ServiceApi The service API response with a reset token
     */
    public function verifyPasswordChangeOtp(array $data): ServiceApi;

    /**
     * Apply a new password after OTP verification.
     *
     * Updates the user's password using the verified reset token.
     * Current session stays active.
     *
     * @param  array  $data  The data containing the reset token and new password
     * @return ServiceApi The service API response indicating password update success
     */
    public function confirmPasswordUpdate(array $data): ServiceApi;

    /**
     * Generate a new 2FA secret and return the QR code URI.
     *
     * The secret is cached temporarily until confirmed.
     *
     * @return ServiceApi The service API response with the QR code URL and secret
     */
    public function setupTwoFactor(): ServiceApi;

    /**
     * Confirm and activate 2FA by validating the first OTP.
     *
     * @param  array  $data  The data containing the 6-digit TOTP code
     * @return ServiceApi The service API response indicating 2FA was enabled
     */
    public function confirmTwoFactor(array $data): ServiceApi;

    /**
     * Disable 2FA after verifying the current TOTP code.
     *
     * @param  array  $data  The data containing the 6-digit TOTP code
     * @return ServiceApi The service API response indicating 2FA was disabled
     */
    public function disableTwoFactor(array $data): ServiceApi;

    /**
     * Complete 2FA authentication during login.
     *
     * Exchanges a challenge token for a full Sanctum token after
     * verifying the TOTP or email OTP code.
     *
     * @param  array  $data  The data containing the challenge token, method, and code
     * @return ServiceApi The service API response with user data and authentication token
     */
    public function verifyTwoFactor(array $data): ServiceApi;

    /**
     * Send a 2FA email OTP for an active challenge token.
     *
     * @param  array  $data  The data containing the challenge token
     * @return ServiceApi The service API response indicating the OTP was sent
     */
    public function resendTwoFactorEmail(array $data): ServiceApi;

    /**
     * Delete the authenticated user's account.
     *
     * Requires password confirmation. All user data and tokens are deleted.
     *
     * @param  array  $data  The data containing the current password
     * @return ServiceApi The service API response indicating account deletion
     */
    public function deleteAccount(array $data): ServiceApi;

    /**
     * Export the authenticated user's personal data as a JSON array.
     *
     * @return ServiceApi The service API response with the user's data
     */
    public function exportData(): ServiceApi;
}
