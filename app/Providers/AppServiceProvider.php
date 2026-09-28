<?php

namespace App\Providers;

use App\Services\Help\HelpProviderInterface;
use App\Services\Help\ZendeskHelpProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Spec F5: "the destination can be swapped...without touching
        // calling code" — this binding is the one place that would change.
        $this->app->bind(HelpProviderInterface::class, ZendeskHelpProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Google is a Socialite core driver; Microsoft/Entra ID needs this
        // package's driver registered explicitly. See App\Http\Controllers\SsoController.
        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);
    }
}
