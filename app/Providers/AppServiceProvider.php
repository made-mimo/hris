<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Help\HelpProviderInterface;
use App\Services\Help\ZendeskHelpProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
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

        // PIM/HRIS alignment §3C item 3. Never in local/testing — that's
        // this dev box's own plain-HTTP Apache setup, and forcing https
        // there would break every generated URL and the test suite's own
        // requests.
        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceScheme('https');
        }

        // PIM/HRIS alignment §3C item 2 — admin-configurable in place of a
        // fixed SESSION_LIFETIME. Skipped for console commands: artisan
        // (including `migrate` on a database that doesn't have the
        // `settings` table yet) has no session to size, and CLI usage
        // shouldn't depend on the settings table already existing.
        if (! $this->app->runningInConsole()) {
            config(['session.lifetime' => Setting::current()->idle_session_timeout_minutes]);
        }
    }
}
