<?php

namespace App\Services\Auth;

use App\Enums\OTPTypeEnum;
use App\Enums\ResponseCode;
use App\Http\Resources\UserResource;
use App\Mail\WelcomeCustomerMail;
use App\Repositories\User\UserRepository;
use App\Services\OTP\OTPService;
use App\Traits\LogAndRespond;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;
use PragmaRX\Google2FA\Google2FA;

class AuthServiceImplement extends ServiceApi implements AuthService
{
    use LogAndRespond;

    public function __construct(
        protected UserRepository $userRepository,
        protected OTPService $otpService,
    ) {}

    /** {@inheritDoc} */
    public function register(array $data): ServiceApi
    {
        try {
            $user = $this->userRepository->createCustomer($data);

            $this->otpService->createAndSendOTP(OTPTypeEnum::VERIFY_EMAIL_OTP->name, $user->email, $user->first_name);
            Mail::to($user->email)->queue(new WelcomeCustomerMail($user->first_name));

            $token = $user->createToken('auth')->plainTextToken;

            return $this->setCode(ResponseCode::CREATED->value)
                ->setMessage('Account created successfully. Please check your email to verify your account.')
                ->setData([
                    'user' => new UserResource($user),
                    'token' => $token,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissionNames(),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Registration failed', ResponseCode::SERVER_ERROR->value);
        }
    }

    /** {@inheritDoc} */
    public function login(array $credentials): ServiceApi
    {
        try {
            $email = $credentials['email'];
            $lockoutKey = 'login_lockout:'.$email;

            if (Cache::has($lockoutKey)) {
                return $this->setCode(423)
                    ->setMessage('Account is temporarily locked. Try again later.');
            }

            $user = $this->userRepository->findByEmail($email);

            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                $attempts = (int) Cache::get('login_attempts:'.$email, 0) + 1;
                Cache::put('login_attempts:'.$email, $attempts, 3600);

                if ($attempts >= 5) {
                    Cache::put($lockoutKey, true, 3600);
                    Cache::forget('login_attempts:'.$email);

                    return $this->setCode(423)
                        ->setMessage('Account is locked due to too many failed attempts. Try again in 1 hour.');
                }

                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('The provided credentials are incorrect.');
            }

            if ($user->two_factor_confirmed_at) {
                $challengeToken = Str::uuid()->toString();
                Cache::put("2fa_challenge_{$challengeToken}", $user->id, now()->addMinutes(5));

                return $this->setCode(ResponseCode::SUCCESS->value)
                    ->setMessage('Two-factor authentication required.')
                    ->setData([
                        'requires_2fa' => true,
                        'challenge_token' => $challengeToken,
                    ]);
            }

            Cache::forget('login_attempts:'.$email);
            Cache::forget($lockoutKey);

            $user->tokens()->delete();
            $token = $user->createToken('auth')->plainTextToken;

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Login successful')
                ->setData([
                    'user' => new UserResource($user),
                    'token' => $token,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissionNames(),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Login failed', ResponseCode::SERVER_ERROR->value);
        }
    }

    /** {@inheritDoc} */
    public function logout($user): ServiceApi
    {
        try {
            $user->currentAccessToken()->delete();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Logged out successfully');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e, 'Logout failed', ResponseCode::SERVER_ERROR->value);
        }
    }

    /** {@inheritDoc} */
    public function forgotPassword(array $data): ServiceApi
    {
        try {
            $user = $this->userRepository->findByEmail($data['email']);
            $pendingToken = Str::random(64);

            if ($user) {
                $this->otpService->createAndSendOTP(OTPTypeEnum::RESET_PASSWORD_OTP->name, $user->email, $user->first_name);
                Cache::put('password_reset_pending:'.hash('sha256', $pendingToken), $user->id, now()->addMinutes(5));
            }

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('If this email is registered, a reset code has been sent.')
                ->setData(['pending_token' => $pendingToken]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function verifyPasswordOtp(array $data): ServiceApi
    {
        try {
            $userId = Cache::pull('password_reset_pending:'.hash('sha256', $data['pending_token']));

            if (! $userId) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired token.');
            }

            $user = $this->userRepository->find($userId);

            if (! $user) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired token.');
            }

            $isValid = $this->otpService->verifyOtpCode(OTPTypeEnum::RESET_PASSWORD_OTP->name, $user->email, $data['otp']);

            if (! $isValid) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired OTP code.');
            }

            $resetToken = Str::random(64);
            Cache::put('password_reset:'.hash('sha256', $resetToken), $user->id, now()->addMinutes(5));

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('OTP verified. You may now reset your password.')
                ->setData(['reset_token' => $resetToken]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function resetPassword(array $data): ServiceApi
    {
        try {
            $userId = Cache::pull('password_reset:'.hash('sha256', $data['reset_token']));

            if (! $userId) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired token.');
            }

            $user = $this->userRepository->findOrFail($userId);
            $this->userRepository->update($user->id, ['password' => $data['password']]);
            $user->tokens()->delete();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Password reset successfully. Please log in with your new password.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function verifyEmail(array $data): ServiceApi
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('Authentication required.');
            }

            if ($user->hasVerifiedEmail()) {
                return $this->setCode(ResponseCode::ALREADY_REPORTED->value)
                    ->setMessage('Email is already verified.')
                    ->setData(['user' => new UserResource($user)]);
            }

            $isValid = $this->otpService->verifyOtpCode(OTPTypeEnum::VERIFY_EMAIL_OTP->name, $user->email, $data['otp']);

            if (! $isValid) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired OTP code.');
            }

            $user->markEmailAsVerified();
            $user->refresh();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Email verified successfully.')
                ->setData(['user' => new UserResource($user)]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function resendVerificationEmail(): ServiceApi
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('Authentication required.');
            }

            if ($user->hasVerifiedEmail()) {
                return $this->setCode(ResponseCode::ALREADY_REPORTED->value)
                    ->setMessage('Email is already verified.')
                    ->setData(['user' => new UserResource($user)]);
            }

            $this->otpService->createAndSendOTP(OTPTypeEnum::VERIFY_EMAIL_OTP->name, $user->email, $user->first_name);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Verification code resent. Please check your email.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function updateEmail(array $data): ServiceApi
    {
        try {
            $user = Auth::user();

            DB::beginTransaction();

            $this->userRepository->update($user->id, ['email' => $data['email']]);
            $user->markEmailAsUnverified();

            DB::commit();

            $this->otpService->createAndSendOTP(OTPTypeEnum::VERIFY_EMAIL_OTP->name, $data['email'], $user->first_name);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Verification OTP sent. Please check your inbox.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function initiatePasswordChange(): ServiceApi
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('Authentication required.');
            }

            $pendingToken = Str::random(64);
            $this->otpService->createAndSendOTP(OTPTypeEnum::RESET_PASSWORD_OTP->name, $user->email, $user->first_name);
            Cache::put('password_update_pending:'.hash('sha256', $pendingToken), $user->id, now()->addMinutes(5));

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Verification OTP sent. Please check your email to confirm the password change.')
                ->setData(['pending_token' => $pendingToken]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function verifyPasswordChangeOtp(array $data): ServiceApi
    {
        try {
            $user = Auth::user();

            $userId = Cache::pull('password_update_pending:'.hash('sha256', $data['pending_token']));

            if (! $userId || $userId !== $user?->id) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired token.');
            }

            $isValid = $this->otpService->verifyOtpCode(OTPTypeEnum::RESET_PASSWORD_OTP->name, $user->email, $data['otp']);

            if (! $isValid) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired OTP code.');
            }

            $resetToken = Str::random(64);
            Cache::put('password_update:'.hash('sha256', $resetToken), $user->id, now()->addMinutes(5));

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('OTP verified. You may now set your new password.')
                ->setData(['reset_token' => $resetToken]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function confirmPasswordUpdate(array $data): ServiceApi
    {
        try {
            $user = Auth::user();

            $userId = Cache::pull('password_update:'.hash('sha256', $data['reset_token']));

            if (! $userId || $userId !== $user?->id) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Invalid or expired token.');
            }

            $this->userRepository->update($user->id, ['password' => $data['password']]);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Password updated successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function setupTwoFactor(): ServiceApi
    {
        try {
            $user = Auth::user();
            $google2fa = app(Google2FA::class);
            $secret = $google2fa->generateSecretKey();

            Cache::put("2fa_setup_{$user->id}", $secret, now()->addMinutes(10));

            $qrCodeUrl = $google2fa->getQRCodeUrl(
                config('app.name'),
                trim("{$user->first_name} {$user->last_name}"),
                $secret
            );

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Scan the QR code with your authenticator app, then confirm with a code.')
                ->setData([
                    'qr_code_url' => $qrCodeUrl,
                    'secret' => $secret,
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function confirmTwoFactor(array $data): ServiceApi
    {
        try {
            $user = Auth::user();
            $secret = Cache::get("2fa_setup_{$user->id}");

            if (! $secret) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('No pending 2FA setup found. Please start setup again.');
            }

            $google2fa = app(Google2FA::class);

            if (! $google2fa->verifyKey($secret, $data['code'])) {
                return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                    ->setMessage('Invalid authentication code.');
            }

            $this->userRepository->update($user->id, [
                'two_factor_secret' => $secret,
                'two_factor_confirmed_at' => now(),
            ]);

            Cache::forget("2fa_setup_{$user->id}");

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Two-factor authentication enabled successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function disableTwoFactor(array $data): ServiceApi
    {
        try {
            $user = Auth::user();

            if (! $user->two_factor_confirmed_at) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Two-factor authentication is not enabled.');
            }

            $google2fa = app(Google2FA::class);

            if (! $google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
                return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                    ->setMessage('Invalid authentication code.');
            }

            $this->userRepository->update($user->id, [
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
            ]);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Two-factor authentication disabled successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function verifyTwoFactor(array $data): ServiceApi
    {
        try {
            $userId = Cache::get("2fa_challenge_{$data['challenge_token']}");

            if (! $userId) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('Invalid or expired challenge token.');
            }

            $user = $this->userRepository->find($userId);

            if (! $user) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('User not found.');
            }

            if ($data['method'] === 'email') {
                $isValid = $this->otpService->verifyOtpCode(
                    OTPTypeEnum::TWO_FACTOR_OTP->name,
                    $user->email,
                    $data['code']
                );

                if (! $isValid) {
                    return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                        ->setMessage('Invalid authentication code.');
                }
            } else {
                $google2fa = app(Google2FA::class);

                if (! $google2fa->verifyKey($user->two_factor_secret, $data['code'])) {
                    return $this->setCode(ResponseCode::VALIDATION_ERROR->value)
                        ->setMessage('Invalid authentication code.');
                }
            }

            Cache::forget("2fa_challenge_{$data['challenge_token']}");

            $token = $user->createToken('auth')->plainTextToken;

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Authentication successful.')
                ->setData([
                    'user' => new UserResource($user),
                    'token' => $token,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissionNames(),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function resendTwoFactorEmail(array $data): ServiceApi
    {
        try {
            $userId = Cache::get("2fa_challenge_{$data['challenge_token']}");

            if (! $userId) {
                return $this->setCode(ResponseCode::UNAUTHORIZED->value)
                    ->setMessage('Invalid or expired challenge token.');
            }

            $user = $this->userRepository->find($userId);

            if (! $user) {
                return $this->setCode(ResponseCode::NOT_FOUND->value)
                    ->setMessage('User not found.');
            }

            $this->otpService->createAndSendOTP(OTPTypeEnum::TWO_FACTOR_OTP->name, $user->email, $user->first_name);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('A verification code has been sent to your email.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function deleteAccount(array $data): ServiceApi
    {
        try {
            $user = Auth::user();
            $userId = $user->id;

            $user->tokens()->delete();
            $user->delete();

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Your account has been deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }

    /** {@inheritDoc} */
    public function exportData(): ServiceApi
    {
        try {
            $user = Auth::user();

            $user->load(['roles', 'permissions']);

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setData([
                    'user' => new UserResource($user),
                    'data' => $user->toArray(),
                ]);
        } catch (\Throwable $e) {
            return $this->logAndRespond($e);
        }
    }
}
