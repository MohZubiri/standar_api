<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Biller\BillerClient;

class BillerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BillerClient::class, function () {
            return new BillerClient();
        });

        $this->app->alias(BillerClient::class, 'biller');
    }

    public function boot(): void
    {
        // No boot logic needed yet
    }
}


