# Identity & Authentication (Sprint 2.2)

`App\Modules\Identity` (docs/DOMAIN_BLUEPRINT.md §8) owns User accounts, authentication, and (starting Sprint 2.3) Roles/Permissions/Teams. This sprint builds `User` on top of Sprint 2.1's `Person`, working Sanctum authentication, account-type derivation, and the Super Admin bypass.

## `User` moved out of `App\Models`

Laravel's `laravel new` scaffold placed a default `User` model at `App\Models\User` — never an intentional architectural decision, just an unclaimed default sitting there since Sprint 0.1. Blueprint §1 explicitly assigns "User accounts (authentication only)" to the **Identity** Foundation module, which `deptrac.yaml`'s layer regex already anticipated. `User` now lives at `App\Modules\Identity\Models\User`, matching `Person`'s placement in `App\Modules\People`.

This touched two previously-frozen sprints' test files (`ApprovalEngineTest`, `PrivateMediaAccessTest`, `FakeRoleHolder`) — but only their `use App\Models\User;` import line. No test behavior, assertion, or business logic changed; `UserFactory` was updated to transparently supply every new required field (`person_id`, `username`, `status`), so none of the frozen sprints' own test intent needed to change. All 124 pre-existing tests passed unmodified in behavior after the move.

## `users` schema matches Blueprint §8 exactly

`username`/`email`/`phone`/`password`/`status`/`last_login_at`, plus `person_id` (the *only* outward FK — one-way, unique: one User per Person), `public_id` (Addendum D4), and `is_super_admin` (the account flag §8's `Gate::before` bypass keys off). Laravel's default `name` and `email_verified_at` columns were dropped — `name` belongs to Person via `PersonName`, never duplicated onto User (§15: "Authentication is not Identity: User ≠ Person"); email verification isn't scoped this sprint.

The `person_id` FK couldn't be added in the original `0001_01_01_000000_create_users_table` migration (it runs before `people` exists in migration order), so it's a separate migration run afterward — the same "additive change via a later migration" pattern already used for Media's `sensitivity`/`SoftDeletes` columns in Sprint 1.3, not a schema-fundamental change requiring the base migration itself to move.

## Login: token-based Sanctum, for both consuming apps

`POST /api/v1/login` accepts `login` (username or email, interchangeably) + `password`, returns a bearer token — no cookie/session SPA flow, since the React admin and Next.js portal may not share a top-level domain. A wrong password and an unknown identifier return the *identical* validation-error shape, so a failed login never reveals whether an account exists. An inactive or suspended account is rejected even with the correct password, checked after credential verification (so the check only ever confirms something an attacker who already has valid credentials could infer anyway).

`POST /api/v1/logout` (behind `auth:sanctum`) deletes the current access token. The test verifying this asserts directly against `PersonalAccessToken` storage rather than a second simulated request with the same revoked token — Sanctum's guard memoizes the resolved user for a test's container lifetime, so a second call in the *same test method* doesn't re-resolve from the database the way a genuinely separate real HTTP request would. What matters (and what Sanctum itself checks on every real request) is that the token record is gone, which the test proves directly.

## Account type: derived, never stored

`App\Modules\Identity\Services\AccountTypeResolver::resolve(User $user): array` returns every account type (`employee`/`student`/`guardian`) implied by which context rows the User's Person holds — never a stored enum on User. It's trivial today (returns `[]` unconditionally) because Employee/Student/Guardian don't exist yet (Sprint 2.4); once they do, the real checks are added directly into this method, not a redesign, since the shape (a service deriving a list, not a column) is exactly what Sprint 2.4 builds on top of.

## Super Admin: a true bypass, not a role

`Gate::before(fn (User $user) => $user->is_super_admin ? true : null)` in `AppServiceProvider::boot()` short-circuits *every* ability check before any policy or role is even consulted. The named risk in this sprint was implementing this as a per-team role grant instead — that would need remembering to re-grant it every time a new branch is created, a silent access-gap. The test proves the actual guarantee: an ability defined *inside the test itself* (simulating "a branch created after this bypass was written") still passes for a super admin with zero configuration, and is denied for an ordinary user, confirming the bypass is additive, not a global override.

## Step-up authentication: mechanism now, delivery later

`App\Modules\Identity\Contracts\StepUpAuthentication` (`challenge()`/`verify()`) is implemented by `StepUpAuthenticationService`, which generates a 6-digit code, stores it against a challenge ID for 5 minutes, and verifies it — genuinely functional mechanics, fully tested (correct code, wrong code, unknown/consumed challenge, cross-user challenge rejection, unverified-contact rejection). What's *not* built: actually sending the code anywhere. That's wired once Notifications exists later this phase; the mechanism itself won't need to change when it is.
