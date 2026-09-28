<?php

namespace App\Providers;

use App\Services\Help\HelpProviderInterface;
use App\Services\Help\ZendeskHelpProvider;
use Illuminate\Support\ServiceProvider;

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
        //
    }
}
