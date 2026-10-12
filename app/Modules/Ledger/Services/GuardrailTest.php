<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Services;

use App\Modules\Ordering\Providers\OrderingServiceProvider;

final class GuardrailTest
{
    public function orderingProvider(): string
    {
        return OrderingServiceProvider::class;
    }
}
