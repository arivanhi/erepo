<?php

namespace App\Providers;

use App\Services\SmtpConfigurationService;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Ensure long-running queue workers always load fresh Google SMTP settings from DB
        Queue::before(function (JobProcessing $event) {
            app(SmtpConfigurationService::class)->applyRuntimeConfig();
        });
    }
}
