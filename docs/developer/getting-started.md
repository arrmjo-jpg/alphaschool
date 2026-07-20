# Developer Getting Started

This is the "how do I, as a developer, do X" reference — distinct from `docs/DOMAIN_BLUEPRINT.md` (what's true about the architecture) and `docs/adr/` (why we decided it). See the Documentation Discipline table in `docs/IMPLEMENTATION_PLAYBOOK.md` for when each gets updated.

## Local setup

Everything runs in Docker — no local PHP, MySQL, Redis, or Node installation. See `docs/developer/docker-development.md` for the full bootstrap sequence and service list; the short version:

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec vite npm install
```

Every command below that reads `vendor/bin/...` or `php artisan ...` runs the same way, just prefixed with `docker compose exec app` — e.g. `docker compose exec app vendor/bin/pint --test`. Several tools (notably Larastan) boot the full Laravel application, which needs the database reachable even for static analysis, not just for running the app — this is automatic inside the `app` container since `mysql` is a real, always-on service on the same Docker network.

## Running the quality gates locally

Run all of these before opening a PR — they're the same commands CI runs. All of them run inside the `app` container (`docker compose exec app <command>`).

| Tool | Command | What it checks |
|---|---|---|
| Pint | `vendor/bin/pint --test` (or without `--test` to auto-fix) | Code formatting |
| Larastan | `vendor/bin/phpstan analyse` | Static analysis / type correctness |
| deptrac | `vendor/bin/deptrac analyse` | Module-boundary rules (Foundation/Domain layering — see Blueprint §2) |
| Pest — Unit | `vendor/bin/pest --testsuite=Unit` | Isolated logic |
| Pest — Feature | `vendor/bin/pest --testsuite=Feature` | End-to-end behavior |
| Pest — Architecture | `vendor/bin/pest --testsuite=Architecture` | Structural rules (Core domain-agnosticism, etc.) |

## Why these tools exist, not just what they do

- **Pint** — deterministic formatting from commit one, so diffs are never muddied by simultaneous reformatting.
- **Larastan** — catches a class of bugs before a test even runs. Starts at level 6 (see `docs/IMPLEMENTATION_PLAYBOOK.md` Technical Debt Register) — don't be surprised it isn't at the strictest level yet; that's deliberate, not an oversight.
- **deptrac** — the single most load-bearing tool in this repo. It's what makes "Domain modules never import each other" an enforced fact instead of a convention someone eventually forgets under deadline pressure. If deptrac fails your PR, that's the architecture telling you the change needs to go through an event or a public service contract instead of a direct import — see Blueprint §2.
- **Pest Architecture tests** — structural assertions Pest's `arch()` expresses more naturally than deptrac's layer config (currently just Core's domain-agnosticism check).

## Module structure

`app/Core/` and `app/Modules/*/` exist as a scaffold from Sprint 0.1 — most are empty except a README describing their eventual responsibility (see each module's `README.md`, sourced from `docs/DOMAIN_BLUEPRINT.md` §1). Do not add code to a module ahead of its planned phase in `docs/IMPLEMENTATION_PLAYBOOK.md` — the folders exist for visibility into the intended architecture, not as an invitation to build early.

## Adopting the temporal pattern (from Sprint 1.1 onward)

Any table that represents a fact which changes over time and must never be silently overwritten (an assignment, a membership, a relationship with a lifecycle) adopts the shared `HasTemporalAssignment` trait rather than inventing its own `effective_from`/`effective_until` handling. See `docs/DOMAIN_BLUEPRINT.md` §6/§7 and Addendum A3/B1, and the trait's own doc-comment once it exists (Sprint 1.1).

## Implementing the Identity Maintenance contracts (from Phase 2 onward)

Any module whose tables hold a `person_id`/`student_id`/`employee_id`/`guardian_id`-shaped reference must implement `ReassignsIdentityReferences` and/or `RedactsPersonalData`, or explicitly declare it holds none. This is checked by an architecture test (Sprint 3.1) that scans for plausible Person-reference columns — silent omission is a CI failure, not a passable gap. See `docs/DOMAIN_BLUEPRINT.md` Addendum C11.

## A real gotcha discovered during Sprint 0.1

Pest's `arch()->toUse()` only detects a dependency on a class that is **actually autoloadable** — a bare `use App\Modules\Whatever\Thing;` import referencing a class that doesn't really exist (no matching file) is silently ignored, even with `composer dump-autoload` run. This was confirmed by direct testing while proving `CoreBoundaryTest.php` catches a real violation: a reference to a non-existent class produced no failure, but the identical reference to a genuinely-existing class failed as expected. Keep this in mind if an architecture test seems to be passing when you'd expect it to fail — check whether the class you're violating the rule with actually exists.

## Filing an ADR

If implementation reveals a genuine gap or ambiguity in the frozen Blueprint, don't quietly resolve it inline — copy `docs/adr/template.md`, fill it in (including alternatives you considered and rejected), get it reviewed, and only then implement against it. Routine implementation decisions that don't touch a frozen Blueprint item don't need one.
