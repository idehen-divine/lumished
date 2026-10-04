<?php

namespace App\Providers;

use App\Enums\SocialProviderEnum;
use App\Services\SocialAuth\AppleServiceImplement;
use App\Services\SocialAuth\GoogleServiceImplement;
use App\Services\SocialAuth\SocialAuthService;
use Illuminate\Support\ServiceProvider;

class SocialAuthServiceProvider extends ServiceProvider
{
    /**
     * Map of provider names to implementation classes.
     *
     * @var array<string, string>
     */
    protected array $implementations = [
        SocialProviderEnum::GOOGLE->name => GoogleServiceImplement::class,
        SocialProviderEnum::APPLE->name => AppleServiceImplement::class,
    ];

    /**
     * Register the correct social auth provider implementation.
     *
     * Unknown providers fall back to the default implementation so the
     * form request validation can reject them with a 422 response.
     */
    public function register(): void
    {
        $this->app->singleton(SocialAuthService::class, function ($app) {
            $request = request();
            $provider = $request?->input('provider');

            if ($provider && isset($this->implementations[$provider])) {
                return $app->make($this->implementations[$provider]);
            }

            return $app->make(GoogleServiceImplement::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
