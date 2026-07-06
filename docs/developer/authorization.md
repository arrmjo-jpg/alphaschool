# Organization, Branch & Authorization (Sprint 2.3)

The full authorization model (`docs/DOMAIN_BLUEPRINT.md` §8) goes live in this sprint: Spatie Teams branch-scoping, Roles, Permissions, Permission Groups, Organization/School/Branch, and Organization Licensing. This sprint was preceded by an explicit design review (no code written until four architectural decisions were confirmed) — this document records what was actually built, not the review itself.

## Spatie Teams was enabled before any real data existed

`config/permission.php`: `teams => true`, `team_foreign_key => 'branch_id'`. This had to happen before the first Role or Permission was created — retrofitting Teams after real assignment data exists means rewriting the unique indexes on all three Spatie pivot tables. The `permission_tables` migration (Sprint 0.1) is config-driven, so flipping the config and running `migrate:fresh` was sufficient; no separate ALTER migration was needed.

An explicit `sanctum` guard was also added to `config/auth.php` (`driver => 'sanctum', provider => 'users'`) — Spatie's guard resolution (`Guard::getNames()`) scans `config('auth.guards')` to decide which guard(s) a Role/Permission may target, and this app's real traffic is entirely token-based, never session/web. Every seeded Role/Permission explicitly sets `guard_name = 'sanctum'`.

**Role assignment always requires a team context first.** `model_has_roles.branch_id` is `NOT NULL` once Teams is enabled — assigning a role without first calling `app(PermissionRegistrar::class)->setPermissionsTeamId($branchId)` throws a DB constraint violation. There is currently no HTTP-level middleware translating "the request's active branch" into this call — that's deferred until a real consumer needs it (the Admin frontend's branch-context switcher), matching "promotion, not prediction." Until then, anything assigning roles (seeders, future services) must set the team context explicitly.

**Roles are global by design — always created with `branch_id = null` explicitly.** Spatie auto-assigns whatever team context happens to be active to a *new* Role unless the attribute is passed explicitly (confirmed by reading `Spatie\Permission\Models\Role`'s `create()` override). Per §8, Roles are "globally defined... branch-scoped only in *assignment*" — `PermissionSeeder` passes `branch_id: null` explicitly on every Role it creates, rather than relying on no ambient team context being set.

## Organization lives in Core; School and Branch live in Identity

Per Addendum A2's literal words ("Organization added to Core") and passing Core's own domain-agnosticism test (vendor/licensing identity is generic to any dedicated-instance B2B product). Branch and School are Identity-Foundation, not Core — consistent with Sprint 1.3's `HasBranchScopedMedia` precedent, which deliberately kept "branch" out of Core for the same reason.

## Branch: immutable `code`, `is_active` not `SoftDeletes`

`code` (e.g. `MC`) is separate from `name_en`/`name_ar` — reports, integrations, accounting, and inventory key off `code`; renaming a branch must never change it. Enforced at the model layer (`saving` hook throws if `code` is dirty on an existing row), not a DB trigger. Format is structural (`^[A-Z0-9]{2,10}$`), validated the same way, not a hardcoded whitelist.

Branch (and Role) use `is_active`, **not** `SoftDeletes` — the one deliberate deviation from every other aggregate built so far (Person, User, Media all got `SoftDeletes`). A "deleted" Branch or Role must never leave `model_has_roles` rows, or any future `branch_id` FK, pointing at a semantically-gone parent — deactivation stops new assignment while historical grants stay meaningful.

## Organization Licensing is relational, not JSON

`organization_modules` (`organization_id`, `module_code`, `enabled`, `licensed_until`) — reconsidered from an initial JSON-array design once per-module expiration became a real requirement. `enabled` + `licensed_until` are structured facts with real query needs (a renewal-reminder job, an expiring-license banner), the same reasoning that already put `Money` in real integer columns instead of a formatted string. `module_code` is validated against a fixed, developer-maintained list (`OrganizationModule::MODULE_CODES`) — Domain modules only; Foundation modules are the base product and are never gated.

The `EnsureModuleIsLicensed` middleware named in `docs/ADMIN_PLATFORM.md` is **not built yet** — no Domain module exists to protect (Admissions is Phase 4). `Organization::hasLicensed(string $moduleCode): bool` is the query the middleware will eventually call.

## Permission naming convention: `{resource}.{action}`

Formalizes a shape already used in Addendum C10 (`identity.approve-merge`), not an invention:

- `resource`: lowercase, `snake_case` if multi-word, plural for a collection-backed entity (`students`, `branches`), singular for a module-level governance concern (`identity`).
- `action`: lowercase, `kebab-case` if multi-word. Five standard verbs — `view`, `create`, `update`, `delete`, `export` — cover ordinary CRUD; anything else gets its own explicit verb (`approve-merge`, `void`, `promote`).
- **No wildcard/`manage` permissions** — a role needing full control over a resource gets every relevant permission granted explicitly, extending "no role inheritance" to permissions themselves.
- **`view` covers both list and single-record read.** Row-level nuance ("a Guardian may view only their own children") is a Policy concern layered on top, never a finer-grained permission string.
- **No module prefix on the resource segment** — that's `permission_group_id`'s job, deliberately decoupled from the code string.

`PermissionSeeder` currently seeds only `branches.*`, `roles.*`, `people.*` — permissions for resources that don't exist yet (`students.*`, `invoices.*`) are **not** fabricated ahead of their own module's first sprint.

## Baseline roles are seeded as a vocabulary, not fully populated

`principal`, `registrar`, `teacher`, `hr_manager`, `accountant` (the job titles named in the original design session) are seeded now so the vocabulary exists, but only `principal` and `registrar` get real permissions today (from what actually exists — Branches, People). `teacher`/`hr_manager`/`accountant` are empty role shells; Academic/HR/Finance attach their own real permissions to these same roles when each ships, rather than this sprint fabricating access to resources that don't exist.

## Direct permission-to-user grants are structurally prevented, not just unused

Spatie's `HasPermissions` trait (pulled in via `HasRoles` on `User`) technically exposes `givePermissionTo()`/`syncPermissions()`/`revokePermissionTo()` on any model — nothing in the package itself prevents direct assignment. `tests/Architecture/NoDirectPermissionGrantTest.php` scans every file under `app/` for these method names and fails if any exist outside the one sanctioned path (`PermissionSeeder` calling `Role::syncPermissions()`, which lives in `database/seeders/`, outside the scan). Proven to actually catch a violation, the same discipline as `deptrac`'s own negative test in Sprint 0.1.

## `DatabaseSeeder` no longer uses `WithoutModelEvents`

A real bug caught while seeding: `WithoutModelEvents` suppresses every Eloquent model event for the whole seeding run, including the `creating()` hooks `HasPublicId` (ULID generation), `Person`'s `search_key` computation, and `Branch`'s code validation now depend on for correctness — Organization rows were silently created with a missing `public_id` until this was found and removed. Re-adding it as a seeding-speed optimization would silently reintroduce that bug.

## Super Admin and branch isolation, re-verified against a real setup

Sprint 2.2's `Gate::before` bypass test used a synthetic `Gate::define()` fixture with no real Branch/Team involved. This sprint re-proves both required guarantees against a real seeded Branch and Role: a role assigned in Branch A grants nothing when the team context switches to Branch B, and a Super Admin passes every check in a Branch created fresh in the test itself, with zero role grant of its own — the exact "covers a branch that didn't exist when the bypass was written" guarantee the Playbook names explicitly.
