# Changelog

All notable changes to AlphaSchool ERP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Changed
- **New standing Sprint completion policy** (`docs/IMPLEMENTATION_PLAYBOOK.md`, "Sprint completion policy" + Definition of Done items 12–14): a sprint is not done, and its Git tag must not be created/moved onto a commit, until tests pass, an explicit ADR compliance review passes, no unresolved architecture finding remains, documentation is updated, and the tag points to that exact approved commit. `v0.2-core-engines` moved from `8b6b357` to `c1f768d` accordingly — the tag now points to the ADR-compliance-fixed commit, not the original Sprint 1.2 commit.

### Fixed
- **ADR compliance review of Sprint 1.3** caught three findings, all fixed before freezing:
  1. **Media's primary key was a plain auto-increment integer**, violating Addendum D4's explicit, named exception for Media ("its primary key should simply be [a ULID] rather than adding a third identifier"). Fixed by making `id` a ULID directly (`database/migrations/2026_07_01_064044_create_media_table.php` now defines `id` via `ulid()->primary()`; the model uses Laravel's `HasUlids` trait) rather than leaving the default int PK plus Spatie's separate `uuid` column as two identifiers doing what Addendum D4 says one should. Spatie's own `uuid` column (used internally by Media Library Pro's JS uploader) was left untouched — it's a distinct, unrelated mechanism.
  2. **The new private-files route was unversioned**, violating Addendum B7 ("`/api/v1` from day one"). Moved under `/api/v1/private-files/{media}` — new code added in this sprint has no excuse to compound the gap, even though the pre-existing `/user` route (Sprint 0.1, frozen) still predates that decision and is out of scope for this review.
  3. **The custom path generator omitted the `{tier}` segment** that both `docs/DOMAIN_BLUEPRINT.md` §12 and the Implementation Playbook's Sprint 1.2.1 spec call for literally. Fixed by reinstating the tier (the media's own disk name) as the first path segment, rather than quietly reinterpreting a frozen spec because the tier also happens to be realized by disk/bucket selection.
  
  See `docs/developer/media-architecture.md`.
- **ADR compliance review of Sprint 1.2** caught a real Core-boundary violation: `ApprovalRequest`/`ApprovalStep` had hard `->constrained('users')` foreign keys, a schema-level dependency from Core into what becomes Identity's Foundation table in Phase 2, violating "Core depends on nothing else in the entire system — not even Foundation modules." Fixed by editing the (still-unreleased, unconsumed) Sprint 1.2 migrations directly rather than layering an `ALTER TABLE` migration on top of a known-wrong original — the actor columns now store a User ID by convention, with referential integrity left to the calling module. Same review added `SoftDeletes` to both models, since approval decisions are evidentiary and nothing should allow that trail to silently disappear via a direct `delete()` call, matching the "the record of a decision must survive" principle already established for Identity Maintenance. See `docs/developer/approval-engine.md`.

### Added

**Sprint 2.3 (Organization, Branch, Roles, Permissions, Permission Groups, Teams):**
- Preceded by an explicit design review before any code was written (four architectural decisions confirmed up front: enable Spatie Teams before any real data exists, Organization in Core / Branch+School in Identity, Branch and Role get `is_active` not `SoftDeletes`, `EnsureModuleIsLicensed` middleware deferred). Three further refinements settled before implementation: Branch gets an immutable `code`, Organization Licensing is relational (`organization_modules`, not a JSON array), and a formal `{resource}.{action}` permission naming convention.
- Spatie Teams enabled (`teams => true`, `team_foreign_key => 'branch_id'`) before a single Role/Permission existed, plus an explicit `sanctum` guard in `config/auth.php` so Spatie's guard resolution matches this app's token-only traffic.
- `App\Core\Models\Organization` + `OrganizationModule` (`organization_id`, `module_code` validated against a fixed Domain-module list, `enabled`, `licensed_until`) — reconsidered from an initial JSON-array design once per-module expiration became a real requirement, the same "structured facts get real columns" reasoning already applied to `Money`.
- `App\Modules\Identity\Models\School`/`Branch` — Branch gets a unique, immutable `code` (distinct from `name_en`/`name_ar`) and `is_active` (not `SoftDeletes` — a "deleted" Branch must never leave `model_has_roles` rows pointing at a semantically-gone parent).
- `App\Modules\Identity\Models\{Role,Permission}` extend Spatie's base classes; `permission_groups` (Translatable) + `permission_group_id` FK on `permissions`. Baseline roles (`principal`, `registrar`, `teacher`, `hr_manager`, `accountant`) seeded as vocabulary — only `principal`/`registrar` get real permissions today, matching what actually exists (Branches, People).
- `tests/Architecture/NoDirectPermissionGrantTest.php`: structural proof (not a UI omission) that Spatie's direct permission-to-user methods are never called anywhere in `app/`, proven to actually catch a violation.
- Fixed a real bug found while seeding: `DatabaseSeeder`'s `WithoutModelEvents` trait was silently suppressing `HasPublicId`'s `creating()` hook (and every other model event), producing rows with missing `public_id`s. Removed.
- Re-verified Sprint 2.2's Super Admin bypass and branch-scoped role isolation against a real seeded Branch/Role/Team setup, not the synthetic `Gate::define()` fixture used when Identity didn't exist yet.
- Sprint 1.2's `ApprovalEngineTest` updated: its `FakeRoleHolder` fixture was explicitly documented as "a stand-in until Spatie Permission is wired onto User (Identity's job, Phase 2)" — this sprint is that moment, so it now exercises the real `HasRoles` trait; the "no hasRole() at all" test now uses `Person` (a real model with no such method) instead of `User` (which now always has one).
- See `docs/developer/authorization.md`.

- **ADR-0008: User login identifiers are independent of Person contacts, plus Preferred Communication Contact.** Raised during Sprint 2.2's approval, before Sprint 2.3 — documentation-only, no implementation change. Resolves an ambiguity Blueprint §8 left open (User's `email`/`phone` vs. Person's `Contact` rows both hold email/phone-shaped data) so every future module treats it the same way: `Contact` (filtered to verified) is the canonical communication channel; `users.email`/`phone` exist only to resolve a login attempt; neither auto-syncs to the other. Extended with the Preferred Communication Contact rule: `Contact.is_primary` is scoped per contact type (a preferred email and a preferred phone are independent), and a shared fallback reasoning governs which contact a workflow should pick when none is designated preferred. Explicitly a **communication-workflow policy, not a data-integrity rule** — multiple verified contacts of the same type are permanently valid, the database enforces no "exactly one preferred" constraint, and whether a given workflow requires a preferred contact before proceeding is that workflow's own business rule to decide and enforce, never a schema-level restriction. See `docs/adr/0008-user-login-identifiers-vs-person-contacts.md`.

**Sprint 2.2 (User, Sanctum authentication, account-type derivation):**
- `App\Modules\Identity\Models\User` relocated from the default `App\Models\User` (an unclaimed `laravel new` scaffold, never an architectural decision) to match Blueprint §1's explicit ownership ("Identity: User accounts, authentication only"). Touched only the `use App\Models\User;` import line in two previously-frozen sprints' tests (`ApprovalEngineTest`, `PrivateMediaAccessTest`, `FakeRoleHolder`) — no test behavior changed; all 124 pre-existing tests still pass. See `docs/developer/identity-auth.md`.
- `users` schema now matches Blueprint §8 exactly: `username`/`email`/`phone`/`password`/`status`/`last_login_at` + `person_id` (one-way, unique FK to `people`) + `public_id` + `is_super_admin`. Dropped Laravel's default `name`/`email_verified_at` — name belongs to Person, never duplicated onto User.
- `POST /api/v1/login`/`logout`: token-based Sanctum auth (API tokens for both the admin SPA and the Next.js portal). Wrong password and unknown identifier return an identical error shape; inactive/suspended accounts are rejected even with correct credentials.
- `App\Modules\Identity\Services\AccountTypeResolver`: account type derived from Person's context rows, never a stored enum — trivial today (no Employee/Student/Guardian exist yet, Sprint 2.4), correct shape now.
- `Gate::before` Super Admin bypass (`AppServiceProvider`): a true bypass keyed off `is_super_admin`, entirely outside the Role system — proven to cover an ability defined *after* the bypass logic already existed, with zero configuration, per the Playbook's named risk (don't implement this as a per-team role grant).
- `App\Modules\Identity\Contracts\StepUpAuthentication` + `StepUpAuthenticationService`: real OTP challenge/verify mechanics (generate, store, verify, expire, reject cross-user use) against a verified `Contact` — actual delivery (SMS/email) deferred to the Notifications module, later this phase.

**Sprint 2.1 / Phase 2 (Person, identity documents, contacts, addresses, duplicate detection):**
- Phase 1 (Core) frozen as of `v0.3-core-media`; Phase 2 (Identity & People Foundation) begins here per `docs/IMPLEMENTATION_PLAYBOOK.md`.
- `App\Core\Concerns\HasPublicId`: ULID `public_id` generated on `creating()`, used as the route key — the dual-ID convention (Addendum D4) starting with its first real consumer, Person.
- `App\Core\ValueObjects\PersonName` (bilingual first/second/third/family × ar/en, per Blueprint §1/§5) and `IdentityDocumentReference` (document_type + issuing_country + number composite, §5) — both explicitly Core value objects, not People's, so `DuplicateDetectionService` can depend on them without importing Person.
- `App\Core\Services\DuplicateDetectionService` (+ `DuplicateSignals`/`DuplicateMatchResult` VOs): domain-agnostic fuzzy AR/EN name matching (Latin consonant-skeleton for transliteration variance, normalized-Arabic + edit-distance fallback), weighted scoring across name/DOB/nationality/identity-document signals. Identity-document evidence is structurally required to reach the "certain" tier — name+DOB+nationality alone (exactly what twins share) caps at 70, below the 80-point threshold, so twins can never score as a hard duplicate.
- `App\Core\Contracts\ReassignsIdentityReferences`/`RedactsPersonalData` (Addendum C3) — minimal signatures now (`reassignPerson`/`anonymizePerson`); the dry-run/preview refinement (Addendum C7) is explicit Sprint 3.2 scope.
- `App\Modules\People\Models\Person` + migration: bilingual identity, `search_key` (computed + indexed from creation, never an afterthought), `SoftDeletes`, `LogsActivity`, ULID `public_id`, `photo` Media collection (private disk, no branch segment — Person is never branch-scoped per Addendum B6). Implements both Identity Maintenance contracts trivially (reassigns/redacts its own children).
- `Contact` (verification status, per §5, needed for Sprint 2.2's step-up auth), `Address`, `PersonIdentityDocument` (DB-level unique on the type+country+number triple; a renewal is a new row, never an overwrite) as separate child entities per People module design.
- `docs/developer/person-identity.md`.

**Sprint 1.3 / Playbook Sprint 1.2.1 (Media Architecture Skeleton):**
- `config/filesystems.php`: `public`/`private`/`temporary` disks, each env-driven (`local` for dev, `s3`-compatible for Cloudflare R2 in production).
- `App\Modules\Media\Support\AlphaSchoolPathGenerator` implementing `{tier}/{branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}`; `App\Modules\Media\Contracts\HasBranchScopedMedia` for opt-in branch partitioning (global entities get no branch folder at all).
- `App\Modules\Media\Models\Media` extends Spatie's base Media model: ULID primary key (`HasUlids`, Addendum D4's deliberate exception to the dual-ID convention), `sensitivity` classification (`standard`/`high`, Addendum B3), `SoftDeletes`, `LogsActivity`.
- `GET /api/v1/private-files/{media}` (`auth:sanctum` + `MediaPolicy`) as the sole serving mechanism for private-tier files — never a raw signed URL. `MediaPolicy` is an explicit, documented placeholder (allow-all-authenticated) until Phase 2 Identity supplies real permissions.
- `App\Modules\Media\Console\Commands\PurgeTemporaryMedia` (`media:purge-temporary --hours --dry-run`), scheduled daily.
- `docs/developer/media-architecture.md`.

**Sprint 1.2 (Core Domain — Number Generator, Approval Engine, Money):**
- `App\Core\ValueObjects\Money`: integer minor-units arithmetic (never floats), structural-only currency validation (no hardcoded currency whitelist, same lesson applied to `ReasonCode`), documented round-half-away-from-zero behavior, currency-mismatch rejection. 35 unit tests.
- `App\Core\Services\NumberGeneratorService` + `number_sequences` table + `App\Core\Models\NumberSequence`: row-locked (`lockForUpdate`) atomic increment, scoped sequences (with non-nullable `''`/`0` sentinels to avoid MySQL's NULL-is-distinct unique-index pitfall), yearly/monthly period reset, `{number}` format-pattern templating. Concurrency safety is *proven*, not just implemented — a genuine dual-connection lock-contention test against real MariaDB (`tests/Feature/Core/NumberGeneratorConcurrencyTest.php`), not a sequential-loop stand-in.
- `App\Core\Services\ApprovalEngine` + `ApprovalRequest`/`ApprovalStep` (polymorphic — the one place in Core polymorphism is correct, since Approval is deliberately shallow, unlike Assignment): sequential all-or-nothing multi-step routing, role-or-user eligibility via a duck-typed `hasRole()` check (no direct Spatie dependency), no-self-approval by default (opt-out, not opt-in).
- `docs/developer/number-generator.md`, `docs/developer/approval-engine.md`.

**Sprint 1.1 (Core Domain — Temporal Pattern):**
- `App\Core\ValueObjects\DateRange` (half-open `[from, until)` interval, overlap/contains logic, 14 edge-case unit tests), `App\Core\ValueObjects\ReasonCode` (pure structural value object), `reason_codes` lookup table + `App\Core\Models\ReasonCode` + `App\Core\Rules\ValidReasonCode` (DB-backed validation, kept separate from the value object), `App\Core\Concerns\HasTemporalAssignment` trait (overlap-guarded saving, `asOf()`/`active()` scopes, `closeAssignment()`/`cancelAssignment()`), `docs/developer/temporal-pattern.md` (later extended with a documented scoping pitfall for many-to-many cases, e.g. `employee_branches`). `RefreshDatabase` enabled globally for Feature tests (was scaffolded but unused).

**Admin Platform:**
- `docs/ADMIN_PLATFORM.md` formally adopted as the official companion reference to `docs/DOMAIN_BLUEPRINT.md` for all React Admin work — same governance weight, no redesign without an ADR. Workspace-based Admin UX architecture (deliberately separate from the Domain Blueprint — a platform/UX decision, not a domain redesign).

**Sprint 0.1 (Engineering Bootstrap):**
- Repository bootstrap: git monorepo initialized (`backend/`, `admin/`, `frontend/`).
- Engineering tooling: Pint, Larastan, deptrac, Pest (unit/feature/architecture suites).
- CI pipeline (GitHub Actions) for `backend/`, path-filtered, running the full quality-gate suite on every PR, including a MariaDB service container.
- `app/Core` and `app/Modules/*` skeleton (README-only placeholders per the adopted architecture, `docs/DOMAIN_BLUEPRINT.md` §1).
- Architecture Decision Records (`docs/adr/`) backfilling the frozen decisions from the Domain Blueprint.
- Developer onboarding guide (`docs/developer/getting-started.md`).
- Repository governance: PR template, CODEOWNERS, Dependabot.

### Known issues
- Larastan (`vendor/bin/phpstan analyse`) fails silently on this Windows development machine with zero output on any real command, even after the local database issue found in Sprint 0.1 was resolved — suspected Windows Defender interference or a PHP 8.4.12 compatibility issue with PHPStan 2.2.3/Larastan 3.x. Not yet reproduced or ruled out on CI (Linux). Tracked, not blocking.
