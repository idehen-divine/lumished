<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateEmailRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    /**
     * Register a new customer account.
     *
     * Creates a new user, assigns the CUSTOMER role, and returns a Sanctum token.
     * A verification OTP is sent to the user's email.
     * In non-production environments the OTP is always 111111.
     *
     * @bodyParam first_name string required The user's first name. Example: John
     * @bodyParam last_name string required The user's last name. Example: Doe
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required Min 8 characters, at least 1 uppercase letter and 1 digit. Must be confirmed. Example: Password1
     * @bodyParam password_confirmation string required Must match password. Example: Password1
     *
     * @response 201 scenario="Success" {
     *     "code": 201,
     *     "message": "Account created successfully. Please check your email to verify your account.",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": null,
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 15:30:00"
     *         },
     *         "token": "1|abc123def456ghi789jkl012mno345pqr",
     *         "roles": ["CUSTOMER"],
     *         "permissions": []
     *     }
     * }
     * @response 422 scenario="Validation Error" {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "email": ["The email has already been taken."]
     *     }
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->authService->register($request->validated())->toJson();
    }

    /**
     * Log in a customer.
     *
     * Authenticates with email and password. Returns a Sanctum token.
     * Revokes all previous tokens (single device per user).
     * After 5 failed attempts the account is locked for 1 hour.
     * If 2FA is enabled, returns a challenge token instead of an auth token.
     *
     * @bodyParam email string required The user's email address. Example: john@example.com
     * @bodyParam password string required The user's password. Example: Password1
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Login successful",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00"
     *         },
     *         "token": "1|xyz789abc123def456ghi789jkl012mno",
     *         "roles": ["CUSTOMER"],
     *         "permissions": []
     *     }
     * }
     * @response 200 scenario="2FA Required" {
     *     "code": 200,
     *     "message": "Two-factor authentication required.",
     *     "data": {
     *         "requires_2fa": true,
     *         "challenge_token": "550e8400-e29b-41d4-a716-446655440000"
     *     }
     * }
     * @response 401 scenario="Invalid Credentials" {
     *     "code": 401,
     *     "message": "The provided credentials are incorrect."
     * }
     * @response 423 scenario="Account Locked" {
     *     "code": 423,
     *     "message": "Account is locked due to too many failed attempts. Try again in 1 hour."
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->authService->login($request->validated())->toJson();
    }

    /**
     * Log out the current customer.
     *
     * Revokes the current Sanctum token.
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Logged out successfully"
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @authenticated
     */
    public function logout(): JsonResponse
    {
        return $this->authService->logout(auth()->user())->toJson();
    }

    /**
     * Request a password reset OTP.
     *
     * Sends a 6-digit OTP to the user's email if the email is registered.
     * Returns a pending token needed for the next step.
     * Does not reveal whether the email exists.
     *
     * @bodyParam email string required The registered email address. Example: john@example.com
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "If this email is registered, a reset code has been sent.",
     *     "data": {
     *         "pending_token": "x8kL3mN9pQ2rV7wZ5tY1bC4dF6gH0jS"
     *     }
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->authService->forgotPassword($request->validated())->toJson();
    }

    /**
     * Verify the password reset OTP.
     *
     * Validates the OTP and returns a reset token that can be used
     * to actually change the password.
     *
     * @bodyParam pending_token string required The token from the forgot password response. Example: x8kL3mN9pQ2rV7wZ5tY1bC4dF6gH0jS
     * @bodyParam otp string required The 6-digit OTP sent to the email. Example: 111111
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "OTP verified. You may now reset your password.",
     *     "data": {
     *         "reset_token": "aB3cD5eF7gH9iJ1kL2mN4oP6qR8sT0uV"
     *     }
     * }
     * @response 400 scenario="Invalid OTP" {
     *     "code": 400,
     *     "message": "Invalid or expired OTP code."
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function verifyPasswordOtp(VerifyOtpRequest $request): JsonResponse
    {
        return $this->authService->verifyPasswordOtp($request->validated())->toJson();
    }

    /**
     * Reset password with the verified reset token.
     *
     * Consumes the reset token and updates the password.
     * All existing tokens are revoked. User must log in again.
     *
     * @bodyParam reset_token string required The token from OTP verification. Example: aB3cD5eF7gH9iJ1kL2mN4oP6qR8sT0uV
     * @bodyParam password string required New password (min 8 chars, at least 1 uppercase, 1 digit). Must be confirmed. Example: NewPass1
     * @bodyParam password_confirmation string required Must match password. Example: NewPass1
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Password reset successfully. Please log in with your new password."
     * }
     * @response 400 scenario="Invalid Token" {
     *     "code": 400,
     *     "message": "Invalid or expired token."
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->authService->resetPassword($request->validated())->toJson();
    }

    /**
     * Verify the authenticated user's email.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * @bodyParam otp string required The 6-digit OTP sent to the email. Example: 111111
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Email verified successfully.",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 15:35:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 208 scenario="Already Verified" {
     *     "code": 208,
     *     "message": "Email is already verified.",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     * @response 400 scenario="Invalid OTP" {
     *     "code": 400,
     *     "message": "Invalid or expired OTP code."
     * }
     *
     * @authenticated
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        return $this->authService->verifyEmail($request->validated())->toJson();
    }

    /**
     * Resend the email verification OTP.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Verification code resent. Please check your email."
     * }
     * @response 208 scenario="Already Verified" {
     *     "code": 208,
     *     "message": "Email is already verified.",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00"
     *         }
     *     }
     * }
     *
     * @authenticated
     */
    public function resendVerificationEmail(): JsonResponse
    {
        return $this->authService->resendVerificationEmail()->toJson();
    }

    /**
     * Update the authenticated user's email.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * The new email is marked unverified. A verification OTP is sent to the new address.
     *
     * @bodyParam email string required The new email address. Example: newemail@example.com
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Verification OTP sent. Please check your inbox."
     * }
     * @response 422 scenario="Duplicate Email" {
     *     "message": "The given data was invalid.",
     *     "errors": {
     *         "email": ["The email has already been taken."]
     *     }
     * }
     *
     * @authenticated
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        return $this->authService->updateEmail($request->validated())->toJson();
    }

    /**
     * Initiate a password change.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Sends a verification OTP to the user's email.
     * The OTP must be verified before the password can be changed.
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Verification OTP sent. Please check your email to confirm the password change.",
     *     "data": {
     *         "pending_token": "x8kL3mN9pQ2rV7wZ5tY1bC4dF6gH0jS"
     *     }
     * }
     *
     * @authenticated
     */
    public function initiatePasswordChange(): JsonResponse
    {
        return $this->authService->initiatePasswordChange()->toJson();
    }

    /**
     * Verify OTP for password change.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * @bodyParam pending_token string required The token from initiate password change. Example: x8kL3mN9pQ2rV7wZ5tY1bC4dF6gH0jS
     * @bodyParam otp string required The 6-digit OTP sent to the email. Example: 111111
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "OTP verified. You may now set your new password.",
     *     "data": {
     *         "reset_token": "aB3cD5eF7gH9iJ1kL2mN4oP6qR8sT0uV"
     *     }
     * }
     * @response 400 scenario="Invalid OTP" {
     *     "code": 400,
     *     "message": "Invalid or expired OTP code."
     * }
     *
     * @authenticated
     */
    public function verifyPasswordChangeOtp(VerifyOtpRequest $request): JsonResponse
    {
        return $this->authService->verifyPasswordChangeOtp($request->validated())->toJson();
    }

    /**
     * Confirm the password update.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Applies the new password after OTP verification.
     * Current session stays active (unlike password reset).
     *
     * @bodyParam reset_token string required The token from OTP verification. Example: aB3cD5eF7gH9iJ1kL2mN4oP6qR8sT0uV
     * @bodyParam password string required New password (min 8 chars, at least 1 uppercase, 1 digit). Must be confirmed. Example: NewPass1
     * @bodyParam password_confirmation string required Must match password. Example: NewPass1
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Password updated successfully."
     * }
     *
     * @authenticated
     */
    public function confirmPasswordUpdate(ResetPasswordRequest $request): JsonResponse
    {
        return $this->authService->confirmPasswordUpdate($request->validated())->toJson();
    }

    /**
     * Generate a new 2FA secret.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Returns a TOTP secret and QR code URL for the authenticator app.
     * The secret must be confirmed with a valid TOTP code before activation.
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Scan the QR code with your authenticator app, then confirm with a code.",
     *     "data": {
     *         "qr_code_url": "otpauth://totp/SHELFIE:john@example.com?secret=JBSWY3DPEHPK3PXP&issuer=SHELFIE",
     *         "secret": "JBSWY3DPEHPK3PXP"
     *     }
     * }
     *
     * @authenticated
     */
    public function setupTwoFactor(): JsonResponse
    {
        return $this->authService->setupTwoFactor()->toJson();
    }

    /**
     * Confirm and activate two-factor authentication.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Validates the TOTP code from the authenticator app and activates 2FA.
     *
     * @bodyParam code string required The 6-digit TOTP code from the authenticator app. Example: 123456
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Two-factor authentication enabled successfully."
     * }
     * @response 422 scenario="Invalid Code" {
     *     "code": 422,
     *     "message": "Invalid authentication code."
     * }
     *
     * @authenticated
     */
    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        return $this->authService->confirmTwoFactor($request->validated())->toJson();
    }

    /**
     * Disable two-factor authentication.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Requires a valid TOTP code from the authenticator app to disable.
     *
     * @bodyParam code string required The 6-digit TOTP code from the authenticator app. Example: 123456
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Two-factor authentication disabled successfully."
     * }
     * @response 422 scenario="Invalid Code" {
     *     "code": 422,
     *     "message": "Invalid authentication code."
     * }
     *
     * @authenticated
     */
    public function disableTwoFactor(DisableTwoFactorRequest $request): JsonResponse
    {
        return $this->authService->disableTwoFactor($request->validated())->toJson();
    }

    /**
     * Complete two-factor authentication during login.
     *
     * Exchanges a challenge token for a full Sanctum token after
     * verifying the TOTP or email OTP code.
     *
     * @bodyParam challenge_token string required The challenge token from login response. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam method string required Verification method. Must be "totp" or "email". Example: totp
     * @bodyParam code string required The 6-digit code (TOTP or email OTP). Example: 123456
     *
     * @response 200 scenario="Success" {
     *     "code": 200,
     *     "message": "Authentication successful.",
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": "2026-07-21 15:00:00",
     *             "created_at": "2026-07-21 12:00:00"
     *         },
     *         "token": "1|def789abc123ghi456jkl789mno012pqr",
     *         "roles": ["CUSTOMER"],
     *         "permissions": []
     *     }
     * }
     * @response 401 scenario="Invalid Challenge" {
     *     "code": 401,
     *     "message": "Invalid or expired challenge token."
     * }
     * @response 422 scenario="Invalid Code" {
     *     "code": 422,
     *     "message": "Invalid authentication code."
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        return $this->authService->verifyTwoFactor($request->validated())->toJson();
    }

    /**
     * Resend a 2FA email OTP.
     *
     * Sends an OTP to the user's email for two-factor authentication.
     * An active challenge token is required.
     *
     * @bodyParam challenge_token string required The challenge token from login response. Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "A verification code has been sent to your email."
     * }
     * @response 401 scenario="Invalid Challenge" {
     *     "code": 401,
     *     "message": "Invalid or expired challenge token."
     * }
     *
     * @group Customer Management
     *
     * @subgroup Authentication
     *
     * @unauthenticated
     */
    public function resendTwoFactorEmail(Request $request): JsonResponse
    {
        return $this->authService->resendTwoFactorEmail($request->validate([
            'challenge_token' => ['required', 'string', 'uuid'],
        ]))->toJson();
    }

    /**
     * Delete the authenticated user's account.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Requires the current password. All user data and tokens are deleted.
     *
     * @bodyParam password string required The current password for confirmation. Example: Password1
     *
     * @response 200 {
     *     "code": 200,
     *     "message": "Your account has been deleted successfully."
     * }
     *
     * @authenticated
     */
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        return $this->authService->deleteAccount($request->validated())->toJson();
    }

    /**
     * Export the authenticated user's personal data.
     *
     * @group Customer Management
     *
     * @subgroup Account
     *
     * Returns all user data as JSON for GDPR compliance.
     *
     * @response 200 {
     *     "code": 200,
     *     "data": {
     *         "user": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "full_name": "John Doe",
     *             "role": ["CUSTOMER"],
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00"
     *         },
     *         "data": {
     *             "id": "01953801-abcd-1234-5678-1234567890ab",
     *             "first_name": "John",
     *             "last_name": "Doe",
     *             "other_name": null,
     *             "email": "john@example.com",
     *             "phone_no": null,
     *             "profile_image": null,
     *             "gender": null,
     *             "status": "ACTIVE",
     *             "email_verified_at": "2026-07-21 14:00:00",
     *             "two_factor_confirmed_at": null,
     *             "created_at": "2026-07-21 12:00:00",
     *             "updated_at": "2026-07-21 15:00:00",
     *             "roles": [...],
     *             "permissions": [...]
     *         }
     *     }
     * }
     *
     * @authenticated
     */
    public function exportData(): JsonResponse
    {
        return $this->authService->exportData()->toJson();
    }
}
