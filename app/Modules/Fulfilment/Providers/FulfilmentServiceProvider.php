<?php

declare(strict_types=1);

namespace App\Modules\Fulfilment\Providers;

use Illuminate\Support\ServiceProvider;

final class FulfilmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fulfilment');
    }
}
