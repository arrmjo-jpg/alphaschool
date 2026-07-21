# Docker Development Environment

This is the standard way to run AlphaSchool ERP locally. Every service the backend needs — MySQL/MariaDB, Redis, Meilisearch, Mailpit, PHP, Nginx, the admin SPA's Vite dev server — runs inside Docker. Nothing needs to be installed on the host beyond Docker itself: no local PHP, no local MySQL, no local Redis, no local Node (Node only matters for the `vite` container's own image build, not for anything you run directly).

This replaces the previous Laragon-based local-install workflow entirely — not a parallel option, the one way now.

## Prerequisites

- Docker Desktop (Windows: WSL2 backend enabled)
- That's it. No PHP, Composer, MySQL, Redis, or Node installation on the host.

## Bootstrap (clean clone → running app)

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan administration:sync-settings
docker compose exec app php artisan administration:sync-providers
docker compose run --rm vite npm install

# vendor/ and node_modules are named volumes that started empty, so
# queue/scheduler (which run `php artisan ...` immediately on boot) and
# vite (which runs `npm run dev` immediately on boot) all crash-looped
# before the installs above finished. Bring them up now that the
# volumes are actually populated:
docker compose up -d queue scheduler vite
```

That's the whole thing, verified end-to-end against a real clean run. `migrate --seed` runs `database/seeders/DatabaseSeeder.php`, which creates a Super Admin (`testuser`/`test@example.com`), baseline roles/permissions, one Organization/School/Branch (the dedicated-instance model, ADR-0006, means exactly one of each), and lookup tables (reason codes, relationship types) -- but deliberately *not* Configuration/Provider Registry data, which lives in code, not seed rows; `administration:sync-settings`/`administration:sync-providers` populate those from every `DeclaresSettingsSchema`/`DeclaresProviderSlots` implementer instead (see `docs/ADMINISTRATION_PLATFORM.md`'s Registry Pattern). After this:

| What | Where |
|---|---|
| Backend API | http://localhost:8000 |
| Admin SPA (Vite, HMR) | http://localhost:5173 |
| Mailpit (catches every outgoing email) | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |
| MySQL/MariaDB (for a GUI client) | `localhost:3306`, user `alphaschool` / password `secret` |
| Redis (for a GUI client) | `localhost:6379` |

`docker compose up -d` on its own (without `--build`) is enough for every day after the first clone — images are only rebuilt when a Dockerfile changes.

## Why each setup command is a separate step, not baked into the image

Composer/npm installs and `migrate`/`key:generate` are explicit commands you run, not something that happens automatically on every `docker compose up`. This matches how the project already treats setup everywhere else — nothing silently mutates the database or regenerates secrets as a side effect of starting a container. `backend/vendor` and the workspace's root `node_modules` live in named Docker volumes (not bind-mounted from the Windows host) specifically so these installs are fast — bind-mounting `vendor/`/`node_modules` through Docker Desktop's WSL2 layer onto NTFS is a well-known severe I/O penalty.

## Services

| Service | Image | Purpose |
|---|---|---|
| `nginx` | `nginx:1.27-alpine` | reverse proxy → `app:9000`, published on host `8000` |
| `app` | `backend/Dockerfile` (php-fpm 8.4) | the Laravel API itself |
| `queue` | same image as `app` | `php artisan queue:work`, runs continuously |
| `scheduler` | same image as `app` | `php artisan schedule:work` — covers the daily `PurgeTemporaryMedia` job (routes/console.php) |
| `vite` | `admin/Dockerfile` (node 22) | the admin SPA's Vite dev server, HMR on host `5173` |
| `mysql` | `mariadb:11.4` | primary database |
| `redis` | `redis:7-alpine` | cache, session, and queue driver |
| `meilisearch` | `getmeili/meilisearch:v1.11` | provisioned ahead of `laravel/scout` actually being installed — inert today, see `docs/DOMAIN_BLUEPRINT.md`'s search-backend decision |
| `mailpit` | `axllent/mailpit` | catches every outgoing email in dev, nothing ever leaves the network |

All nine join one bridge network (`alphaschool`) and address each other by service name — `DB_HOST=mysql`, `REDIS_HOST=redis`, `MEILISEARCH_HOST=http://meilisearch:7700`, `MAIL_HOST=mailpit`. These live in two places that must agree: `backend/.env.example` (what a fresh `cp .env.example .env` produces) and the root `.env.docker` (what `docker compose` injects into the `app`/`queue`/`scheduler` containers before that copy step has even run, so the containers boot with real connectivity from the very first `up`). Both already agree — you shouldn't need to touch either for normal development.

The two deliberate exceptions to "never localhost" are `APP_URL`, `CORS_ALLOWED_ORIGINS`, and `admin/.env`'s `VITE_API_URL` — those describe what the developer's own browser sees, and the browser runs on the host, outside the Docker network, reaching each service through its published port. That's not the same thing as one container reaching another.

## Common commands

```bash
# Any artisan command
docker compose exec app php artisan tinker
docker compose exec app php artisan test
docker compose exec app php artisan test --testsuite=Feature

# Composer
docker compose exec app composer require some/package

# Pint / Larastan / deptrac (the same gates CI runs)
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse
docker compose exec app vendor/bin/deptrac analyse

# npm, from the workspace root (admin/ + packages/contracts share one workspace)
docker compose exec vite npm install some-package --workspace=admin
docker compose exec vite npm run lint --workspace=admin

# Tail logs for one service
docker compose logs -f app
docker compose logs -f queue

# Stop everything (data survives, it's in named volumes)
docker compose down

# Stop everything AND wipe all data (fresh database, fresh Redis, etc.)
docker compose down -v
```

## The two real-connection tests

`tests/Feature/Core/NumberGeneratorConcurrencyTest.php`, `ConfigurationValueLockingConcurrencyTest`, and `ProviderCredentialLockingConcurrencyTest` deliberately open two independent real MariaDB connections to prove row-level locking, bypassing the test suite's normal in-memory SQLite connection (see each file's own docblock). This works exactly the same way in Docker as it did against Laragon — as long as you run tests **inside** the `app` container (`docker compose exec app php artisan test`), `mysql` resolves correctly on the shared network. Running `php artisan test` from the host wouldn't work regardless of Docker, since there's no local PHP to run it with in the first place.

## Troubleshooting

- **`docker compose exec app ...` fails with "no such service" or connection refused**: the `app` container probably hasn't finished starting, or `mysql`/`redis` haven't passed their healthcheck yet. `docker compose ps` shows status; `app`/`queue`/`scheduler` all wait on `mysql`/`redis` being healthy before starting, so a fresh `up` can take a few seconds longer than it looks.
- **Vite HMR doesn't reflect a file change**: confirm the `vite` container's volumes actually mounted (`docker compose exec vite ls /workspace/admin/src`) — if you're on Windows and seeing this, check Docker Desktop's file sharing settings include the repo's drive.
- **A `composer install`/`npm install` you just ran seems to have vanished**: expected if you ran it on the host instead of inside the container (`docker compose exec app ...` / `docker compose exec vite ...`) — `vendor/`/`node_modules` are named volumes, invisible to a host-side install.
- **Port already in use** (`8000`, `5173`, `3306`, `6379`, `7700`, `8025`): something else on the host (e.g. a lingering Laragon service) is bound to that port. Stop it, or change the left-hand side of the relevant `ports:` mapping in `docker-compose.yml`.
- **`vite` crash-loops with `Cannot find native binding` / `@rolldown/binding-linux-x64-gnu`**: npm's own well-documented optional-dependencies bug (npm/cli#4828) — this happens if `package-lock.json` was ever generated by running `npm install` natively on a different OS (e.g. Windows) instead of inside the container, since the lockfile then pins the wrong platform's native binary. The root `package-lock.json` in this repo was regenerated from inside the Linux container specifically to avoid this; if it happens again, the fix is `docker compose run --rm vite sh -c "rm -rf node_modules admin/node_modules package-lock.json && npm install"` (clearing volume *contents*, not the mount points themselves — `rm -rf node_modules` fails with "Device or resource busy" if run against the volume's own mount directory).
- **Backend API returns 502 after rebuilding/recreating the `app` container**: shouldn't happen — `docker/nginx/default.conf` uses Docker's embedded resolver with a variable-based `fastcgi_pass` specifically so it re-resolves `app`'s IP on every request instead of caching it at startup. If it does happen anyway, `docker compose restart nginx` is the fallback.
- **`php artisan test` seems to have wiped your dev database**: this happened for real once (2026-07-21) — `phpunit.xml`'s `<env>`/`<server>` overrides were being silently defeated by `.env.docker`'s real container-level environment variables, so tests ran `RefreshDatabase`'s `migrate:fresh` against the real MariaDB dev database instead of an isolated in-memory SQLite one. Fixed in `phpunit.xml` (both an `<env force="true">` and a matching `<server>` entry are required for every variable Docker also sets — see the inline comment there for why one alone isn't enough). If you ever see this again, `docker compose exec app php artisan db:seed` + `storage:link` + `administration:sync-settings` + `administration:sync-providers` restores the working dev state; see `docs/developer/rca-2026-07-21-test-database-wipe.md` for the full incident writeup and reproduction steps before assuming it's the same cause.
