<?php

declare(strict_types=1);

use App\Modules\Cart\Providers\CartServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Fulfilment\Providers\FulfilmentServiceProvider;
use App\Modules\Identity\Providers\IdentityServiceProvider;
use App\Modules\Inventory\Providers\InventoryServiceProvider;
use App\Modules\Ledger\Providers\LedgerServiceProvider;
use App\Modules\Moderation\Providers\ModerationServiceProvider;
use App\Modules\Ordering\Providers\OrderingServiceProvider;
use App\Modules\Payments\Providers\PaymentsServiceProvider;
use App\Modules\Support\Providers\SupportServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    IdentityServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    CartServiceProvider::class,
    OrderingServiceProvider::class,
    PaymentsServiceProvider::class,
    FulfilmentServiceProvider::class,
    LedgerServiceProvider::class,
    ModerationServiceProvider::class,
    SupportServiceProvider::class,
];
