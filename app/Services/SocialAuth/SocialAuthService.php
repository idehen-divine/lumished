<?php

namespace App\Services\SocialAuth;

use L0n3ly\LaravelRepositoryWithService\Contracts\BaseService;

interface SocialAuthService extends BaseService
{
    /**
     * Authenticate a user via a social provider Firebase ID token.
     *
     * Verifies the Firebase ID token, finds or creates a user by email,
     * and returns the same response format as the standard login.
     *
     * @param  mixed  $request  Request containing provider and access_token
     * @param  string  $role  Role to assign if creating a new user (CUSTOMER, ADMIN)
     * @return SocialAuthService Returns service response with user data and access token
     */
    public function socialLogin($request, string $role): SocialAuthService;
}
