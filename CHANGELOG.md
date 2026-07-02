# Changelog

All notable changes to AlphaSchool ERP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

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
