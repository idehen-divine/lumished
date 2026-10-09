<?php

namespace App\Services\SocialAuth;

use App\Enums\ResponseCode;
use App\Http\Resources\UserResource;
use App\Repositories\User\UserRepository;
use App\Traits\LogAndRespond;
use Kreait\Firebase\Factory;
use L0n3ly\LaravelRepositoryWithService\Services\ServiceApi;

class GoogleServiceImplement extends ServiceApi implements SocialAuthService
{
    use LogAndRespond;

    /**
     * Initialize the service with user repository dependency.
     *
     * @param  UserRepository  $userRepository  The user repository instance
     */
    public function __construct(
        protected UserRepository $userRepository,
    ) {}

    /**
     * Authenticate a user via Google Firebase OAuth.
     *
     * Verifies the Firebase ID token, finds or creates a user by email,
     * and returns the same response format as the standard login.
     *
     * @param  mixed  $request  Request containing provider and access_token
     * @param  string  $role  Role to assign if creating a new user (CUSTOMER, ADMIN)
     * @return SocialAuthService Returns service response with user data and access token
     */
    public function socialLogin($request, string $role): SocialAuthService
    {
        try {
            $validated = $request->validated();

            $credentials = config('services.firebase.credentials');
            $trimmed = is_string($credentials) ? ltrim($credentials) : '';
            $isInlineJson = $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[');

            if (empty($credentials) || (is_string($credentials) && ! $isInlineJson && ! is_file($credentials))) {
                return $this->setCode(ResponseCode::SERVER_ERROR->value)
                    ->setMessage('Social login is not configured. Please try again later.');
            }

            $firebase = (new Factory)
                ->withServiceAccount($credentials);

            $verifiedToken = $firebase->createAuth()->verifyIdToken($validated['access_token']);

            $firebaseEmail = $verifiedToken->claims()->get('email');
            $firebaseName = $verifiedToken->claims()->get('name');
            $firebasePicture = $verifiedToken->claims()->get('picture');

            if (! $firebaseEmail) {
                return $this->setCode(ResponseCode::BAD_REQUEST->value)
                    ->setMessage('Email not provided by Google. Please ensure your Google account has a verified email.');
            }

            $user = $this->userRepository->findByEmail($firebaseEmail);

            if (! $user) {
                $nameParts = $firebaseName ? explode(' ', $firebaseName, 2) : ['', ''];

                $createMethod = 'create'.ucfirst(strtolower($role));

                $user = $this->userRepository->{$createMethod}([
                    'first_name' => $nameParts[0] ?? '',
                    'last_name' => $nameParts[1] ?? '',
                    'email' => $firebaseEmail,
                    'profile_image' => $firebasePicture ?? null,
                ]);

                $user->markEmailAsVerified();
            }

            $user->tokens()->delete();
            $token = $user->createToken('api')->plainTextToken;

            return $this->setCode(ResponseCode::SUCCESS->value)
                ->setMessage('Authentication successful')
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
}
