<?php

declare(strict_types=1);

namespace App\Modules\Moderation\Providers;

use Illuminate\Support\ServiceProvider;

final class ModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'moderation');
    }
}
