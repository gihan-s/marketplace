# WBS 0.1 — Repo, branching, CI pipeline, module skeleton

**Estimate:** 3 ideal days · **Depends on:** nothing · **Blocks:** everything

**Done when:** a pull request runs Pint, PHPStan level 8, architecture
tests and Pest against PostgreSQL and Redis in CI, and `main` cannot be
merged into unless all four pass.

---

## Versions to pin

| Component | Version | Note |
|---|---|---|
| Laravel | 13.x | Released Mar 2026, security support to Mar 2028 |
| PHP | **8.5** | Laravel 13 needs 8.3+. 8.5 is what local development runs, so CI and production match it |
| PostgreSQL | **18** | Same major locally, in CI (`postgres:18`) and on the server |
| Redis | 7 | Local: Docker container, see "Local services on Windows" below |
| Node | 22 LTS | Vite build only |
| Composer | 2.x | |

The single most important rule here: **local, CI and production run the
same PHP and PostgreSQL major versions.** A mismatch produces green CI
and a broken deploy.

---

## Step 1 — Create the project

```bash
composer create-project laravel/laravel marketplace
cd marketplace
php artisan --version   # expect 13.x
```

Then set PostgreSQL in `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=marketplace
DB_USERNAME=marketplace
DB_PASSWORD=secret

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
```

Set the Redis drivers **now**, not later. Building against the file
drivers bakes in assumptions that break the moment a second app node
exists.

Mirror these into `.env.example` and commit it. `.env` stays ignored.
Use placeholder values in `.env.example` (`DB_PASSWORD=secret`), never the
real ones.

### Local services on Windows

PHP on Windows is the ZTS build, so it needs the matching extensions.
Enable them in `php.ini` (`php --ini` shows which file is loaded):

```ini
extension=gd
extension=intl
extension=pdo_pgsql
extension=pgsql
extension=redis
```

`redis` is not bundled. Download `php_redis-<ver>-8.5-ts-vs17-x64.zip` from
`https://downloads.php.net/~windows/pecl/releases/redis/` (the build must
match PHP version, **ts** or **nts**, and x64 — `php -i` shows
"Thread Safety" and "Architecture"), copy `php_redis.dll` into the `ext`
directory and verify with `php --ri redis`.

Redis has no native Windows build. Run it in Docker, bound to loopback
only, with AOF on as the technical plan requires:

```powershell
docker run -d --name marketplace-redis --restart unless-stopped `
  -p 127.0.0.1:6379:6379 -v marketplace-redis-data:/data `
  redis:7 redis-server --appendonly yes --appendfsync everysec
```

Create the databases once (the role needs `CREATEDB`): `marketplace` for
development and `marketplace_test` for the test suite.

---

## Step 2 — Git repository and branching

```bash
git init
git add .
git commit -m "chore: initial Laravel 13 skeleton"
git branch -M main
git remote add origin git@github.com:<org>/marketplace.git
git push -u origin main
```

### Branching model

Use **GitHub Flow**, not Gitflow. One long-lived branch, short-lived
feature branches, merge via pull request.

| Branch | Purpose |
|---|---|
| `main` | Always deployable. Protected. |
| `feat/<wbs>-<slug>` | e.g. `feat/1.1-ledger-core` |
| `fix/<slug>` | Bug fixes |
| `chore/<slug>` | Tooling, deps, config |

Gitflow's `develop` and `release` branches exist to coordinate parallel
release trains. You have one deployable target and a staging box — the
extra branches would be ceremony with no payoff.

Name branches after their WBS number. In nine months you will want to
know which work package a commit came from.

### Commit messages

Conventional Commits — `feat:`, `fix:`, `chore:`, `refactor:`, `test:`,
`docs:`. It costs nothing and makes release notes mechanical later.

---

## Step 3 — Module skeleton

Laravel's default `"App\\": "app/"` PSR-4 mapping already covers
`App\Modules\*`, so no Composer changes are needed.

Save this as `make-modules.sh` in the project root, run it once, then
delete it.

```bash
#!/usr/bin/env bash
set -euo pipefail

MODULES=(Identity Catalog Inventory Cart Ordering Payments
         Fulfilment Ledger Moderation Support)

# 1. Shared namespace
mkdir -p app/Shared/{Money,Concerns,Contracts,Tenancy}

# 2. Per-module tree, routes file, views dir, service provider
for M in "${MODULES[@]}"; do
  LOWER=$(echo "$M" | tr '[:upper:]' '[:lower:]')
  BASE="app/Modules/$M"

  mkdir -p "$BASE"/{Models,Services,Policies,Events,Jobs,Livewire,Providers,routes}
  mkdir -p "$BASE"/Http/{Controllers,Requests}
  mkdir -p "$BASE"/resources/views

  cat > "$BASE/routes/web.php" <<EOF
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    //
});
EOF

  cat > "$BASE/Providers/${M}ServiceProvider.php" <<EOF
<?php

declare(strict_types=1);

namespace App\\Modules\\$M\\Providers;

use Illuminate\Support\ServiceProvider;

final class ${M}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \$this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        \$this->loadViewsFrom(__DIR__ . '/../resources/views', '$LOWER');
    }
}
EOF
done

# 3. Keep empty directories in git
find app/Modules app/Shared -type d -empty -exec touch {}/.gitkeep \;

# 4. Regenerate bootstrap/providers.php
{
  echo "<?php"
  echo
  echo "return ["
  echo "    App\\Providers\\AppServiceProvider::class,"
  echo
  for M in "${MODULES[@]}"; do
    echo "    App\\Modules\\$M\\Providers\\${M}ServiceProvider::class,"
  done
  echo "];"
} > bootstrap/providers.php

echo "Created ${#MODULES[@]} modules."
```

Then verify:

```bash
chmod +x make-modules.sh && ./make-modules.sh
php artisan route:list      # boots the app — must not error
rm make-modules.sh
```

### Four things the script handles that are easy to miss

1. **A real `routes/web.php` per module.** `loadRoutesFrom` pointing at
   a missing file throws on boot. A `.gitkeep` is not a routes file.
2. **A `resources/views` directory per module**, for the same reason
   with `loadViewsFrom`.
3. **`.gitkeep` in every empty directory.** Git does not track empty
   directories — without these, the whole skeleton disappears on the
   next clone and you rebuild it from memory.
4. **All ten providers registered** in `bootstrap/providers.php`. A
   module whose provider is not registered simply does nothing, with no
   error to tell you why.

### Why plain directories and not a modules package

`nwidart/laravel-modules` and similar add their own autoloader, their own
artisan commands, and their own migration paths. That is a lot of
machinery fighting Laravel's conventions to buy directory structure you
can create with `mkdir`. Plain namespaced folders plus enforced
dependency rules (step 5) give the same boundaries with nothing to
maintain.

**Keep migrations in `database/migrations`.** Per-module migration paths
mean registering each one and losing the single global timestamp
ordering. Ordering matters more than tidiness when a foreign key crosses
modules.

### Where shared code lives

`app/Shared/` holds things every module may use: the money value object,
the ULID trait, tenant-scoping base classes, and the
`PaymentProvider` / `CourierProvider` / `SmsProvider` contracts. Modules
may depend on `Shared`. `Shared` may depend on no module — that rule is
enforced in step 5.

### Still manual after the script

Nothing structural. Two judgement calls remain yours:

- **Module list.** Ten is the plan's set. Adding or removing one is a
  one-line edit to `MODULES` before you run it.
- **`Support` is a name collision risk.** `App\Modules\Support` sits
  near `Illuminate\Support`. If that reads badly in imports, rename it
  `Notifications` now — renaming after fifty files import it is tedious.

---

## Step 4 — Quality tooling

```bash
composer require --dev larastan/larastan pestphp/pest pestphp/pest-plugin-laravel -W

vendor/bin/pest --init     # creates tests/Pest.php; there is no pest:install command
```

Pint ships with Laravel; no install needed.

Expect **Larastan 3.x** and **Pest 4.x**. The Laravel 13 skeleton already
requires Pest 4 and it works on Laravel 13; Pest 5 also exists and needs
PHP 8.4+, so moving to it later is a deliberate upgrade, not a
requirement. `pest --init` can prompt for input; if it hangs in a
non-interactive terminal, the only file it needs to create is
`tests/Pest.php`.

Omitting version constraints is deliberate. Composer resolves the newest
release compatible with your Laravel version and writes a proper `^X.Y`
constraint into `composer.json` itself, which is both more accurate than
guessing and avoids a Windows trap: `composer` runs as `composer.bat`, so
arguments pass through cmd.exe where `^` is the escape character and gets
stripped — even inside quotes, since PowerShell removes those first. An
unquoted `^3.0` becomes `3.0`, meaning exactly 3.0.0, and Composer then
fails with a confusing conflict naming a Laravel version you are not
using. If you ever do need a caret on Windows, double it: `^^3.0`.

### `pint.json`

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "ordered_imports": { "sort_algorithm": "alpha" },
        "no_unused_imports": true,
        "global_namespace_import": {
            "import_classes": true,
            "import_functions": false,
            "import_constants": false
        }
    }
}
```

`declare_strict_types` is the rule that matters. PHP's default coercion
will silently turn `"12abc"` into `12`, and in a system handling money
and quantities that is exactly the class of bug you cannot afford. Turn
it on now, while there are zero files to fix.

Run `vendor/bin/pint` once and commit the result as a formatting-only
commit.

### `phpstan.neon`

```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - app
        - config
        - database
        - routes

    ignoreErrors:
        # Laravel skeleton: env() is typed bool|string, rtrim() wants string.
        -
            identifier: argument.type
            path: config/filesystems.php
            count: 1
```

PHPStan 2.x removed `checkMissingIterableValueType` and
`checkGenericClassInNonGenericObjectType` (level 6+ enforces them), and it
rejects the config if they are present. The single `ignoreErrors` entry is
for a stock skeleton file; it is the only suppression allowed.

**Level 8 from an empty project is the whole point.** Larastan on a
fresh Laravel 13 skeleton passes clean or near-clean at level 8. Adopted
later, you would need a baseline file holding hundreds of suppressed
errors, and that baseline never shrinks. Starting clean means no
baseline exists and no error is ever acceptable.

Run it immediately:

```bash
vendor/bin/phpstan analyse
```

Fix anything it finds before writing a line of feature code. If
something is genuinely unfixable, add a narrow `ignoreErrors` entry with
a comment explaining why — never a blanket baseline.

---

## Step 5 — Architecture tests

This is what replaces a module package. Pest's architecture testing
enforces boundaries in the normal test run, with no extra tool.

**First, register the suite.** Laravel's `phpunit.xml` defines only
`Unit` and `Feature`, so a `tests/Arch` directory is never discovered —
and a test that does not run looks exactly like a test that passes. Add
a third entry inside `<testsuites>`:

```xml
<testsuite name="Arch">
    <directory>tests/Arch</directory>
</testsuite>
```

**Run `vendor/bin/pint` before the first arch run.** The
`toUseStrictTypes()` rule checks every file under `App`, and Laravel's
skeleton files have no `declare(strict_types=1)` until Pint adds it.

`tests/Arch/ArchTest.php`:

```php
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
```

The module rules work from day one even with empty modules, because each
module namespace already contains its service provider — so
`App\Modules\Ledger` matches a real class rather than nothing.

**The ledger rule is the one that earns its keep.** The ledger must be
callable from anywhere and depend on nothing, so its posting rules stay
the single description of how money moves. The first time someone
imports an Ordering model into a ledger service to "just check the order
status", this test fails and stops it.

Add rules as modules are built. The direction of dependency is: Shared →
Identity → Catalog → Inventory → Cart → Ordering → Fulfilment, with
Ledger and Payments as leaves that others call.

---

## Step 6 — Composer scripts

In `composer.json`:

```json
"scripts": {
    "fix": "pint",
    "lint": "pint --test",
    "stan": "phpstan analyse --no-progress",
    "test": [
        "@php artisan config:clear --ansi @no_additional_args",
        "pest"
    ],
    "check": [
        "@lint",
        "@stan",
        "@test"
    ]
}
```

`composer check` is what CI runs and what you run before pushing. One
command, same result in both places — no "it passed locally" surprises.

---

## Step 7 — CI pipeline

`.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

permissions:
  contents: read

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  check:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:18
        env:
          POSTGRES_USER: marketplace
          POSTGRES_PASSWORD: secret
          POSTGRES_DB: marketplace_test
        ports: ['5432:5432']
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        ports: ['6379:6379']
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    env:
      DB_CONNECTION: pgsql
      DB_HOST: 127.0.0.1
      DB_PORT: 5432
      DB_DATABASE: marketplace_test
      DB_USERNAME: marketplace
      DB_PASSWORD: secret
      REDIS_HOST: 127.0.0.1

    steps:
      - uses: actions/checkout@v4

      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          extensions: pdo_pgsql, redis, intl, bcmath, gd
          coverage: none

      - name: Get Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: composer-${{ hashFiles('composer.lock') }}
          restore-keys: composer-

      - run: composer install --prefer-dist --no-interaction --no-progress

      - run: cp .env.example .env && php artisan key:generate

      - name: Format
        run: composer lint

      - name: Static analysis
        run: composer stan

      - name: Tests
        run: composer test
```

**Run tests against real PostgreSQL and Redis, not SQLite and array
drivers.** SQLite is faster and will cheerfully pass tests that fail in
production — it has different transaction semantics, no row-level
security, and a far looser type system. Since RLS is a core part of your
tenant isolation, testing on SQLite would mean never testing the
isolation at all.

Keep all four checks in one job. Parallel jobs each pay the install cost;
at this size serial is faster and simpler.

---

## Step 8 — Branch protection

On GitHub: Settings → Branches → add a rule for `main`.

- Require a pull request before merging
- Require status checks to pass → select `check`
- Require branches to be up to date before merging
- Do not allow bypassing the above settings

That last one matters. Self-approved merges are fine; **merging red CI is
not**, and leaving yourself an override means using it at 1am.

### Optional: pre-push hook

Catches failures before they reach CI.

```bash
mkdir -p .githooks
cat > .githooks/pre-push <<'EOF'
#!/bin/sh
composer check
EOF
chmod +x .githooks/pre-push
git config core.hooksPath .githooks
```

Commit `.githooks/`; `core.hooksPath` is per-clone and must be set again
on a new machine.

---

## Verification checklist

Work package 0.1 is done when all of these are true:

- [ ] `php artisan --version` reports 13.x
- [ ] `composer check` passes locally with zero errors
- [ ] `vendor/bin/phpstan analyse` is clean at level 8 **with no baseline file**
- [ ] A deliberate violation — `dd()` in a controller, or importing `App\Modules\Ordering` inside `App\Modules\Ledger` — makes `composer check` fail
- [ ] A pull request shows the `check` status, and merge is blocked while it is red
- [ ] `.env.example` is committed and `.env` is not
- [ ] CI tests run against PostgreSQL 18, not SQLite

Test the fourth item explicitly. A guardrail you have never seen fire is
a guardrail you do not know is wired up.

---

## Not in this work package

| Item | Where it belongs |
|---|---|
| Server provisioning, Forge | 0.2 |
| Cloudflare, staging | 0.3 |
| Backups, Sentry, monitoring | 0.4 |
| ULID base model, money object, audit log | 0.5 |
| Tenant scoping, RLS, cross-tenant test harness | 0.6 |
| Provider contracts and fakes | 0.7 |
| Tailwind tokens, Blade components | 0.8 |

Resist starting 0.5 while in here. The temptation is to add the money
class "since the repo is open" — but 0.5 depends on 0.1 being finished
and enforcing, so that the money class is written under level 8 and
strict types from its first line.
