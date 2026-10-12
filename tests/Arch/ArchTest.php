<?php

declare(strict_types=1);

arch('strict types everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug statements ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'exit'])
    ->not->toBeUsed();

arch('shared code depends on no module')
    ->expect('App\Shared')
    ->not->toUse('App\Modules');

arch('ledger is independent')
    ->expect('App\Modules\Ledger')
    ->not->toUse([
        'App\Modules\Ordering',
        'App\Modules\Catalog',
        'App\Modules\Cart',
        'App\Modules\Fulfilment',
        'App\Modules\Payments',
    ]);

arch('identity is independent')
    ->expect('App\Modules\Identity')
    ->not->toUse([
        'App\Modules\Catalog',
        'App\Modules\Ordering',
        'App\Modules\Cart',
    ]);

arch('catalog does not reach forward')
    ->expect('App\Modules\Catalog')
    ->not->toUse([
        'App\Modules\Cart',
        'App\Modules\Ordering',
        'App\Modules\Payments',
        'App\Modules\Fulfilment',
    ]);

// Enable once Services directories hold real classes (1.1 onward).
// Mid-string wildcards are unreliable; verify it actually fires.
// arch('services do not depend on HTTP')
//     ->expect('App\Modules\*\Services')
//     ->not->toUse('Illuminate\Http\Request');
