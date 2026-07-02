# Changelog

All notable changes to AlphaSchool ERP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- **Sprint 1.1 (Core Domain — Temporal Pattern):** `App\Core\ValueObjects\DateRange` (half-open `[from, until)` interval, overlap/contains logic, 14 edge-case unit tests), `App\Core\ValueObjects\ReasonCode` (pure structural value object), `reason_codes` lookup table + `App\Core\Models\ReasonCode` + `App\Core\Rules\ValidReasonCode` (DB-backed validation, kept separate from the value object), `App\Core\Concerns\HasTemporalAssignment` trait (overlap-guarded saving, `asOf()`/`active()` scopes, `closeAssignment()`/`cancelAssignment()`), `docs/developer/temporal-pattern.md`. `RefreshDatabase` enabled globally for Feature tests (was scaffolded but unused).
- `docs/ADMIN_PLATFORM.md` formally adopted as the official companion reference to `docs/DOMAIN_BLUEPRINT.md` for all React Admin work — same governance weight, no redesign without an ADR.
- `docs/ADMIN_PLATFORM.md`: Workspace-based Admin UX architecture decision (deliberately separate from the Domain Blueprint — a platform/UX decision, not a domain redesign).

### Known issues
- Larastan (`vendor/bin/phpstan analyse`) fails silently on this Windows development machine with zero output on any real command, even after the local database issue found in Sprint 0.1 was resolved — suspected Windows Defender interference or a PHP 8.4.12 compatibility issue with PHPStan 2.2.3/Larastan 3.x. Not yet reproduced or ruled out on CI (Linux). Tracked, not blocking.
- Repository bootstrap: git monorepo initialized (`backend/`, `admin/`, `frontend/`).
- Engineering tooling: Pint, Larastan, deptrac, Pest (unit/feature/architecture suites).
- CI pipeline (GitHub Actions) for `backend/`, path-filtered, running the full quality-gate suite on every PR.
- `app/Core` and `app/Modules/*` skeleton (README-only placeholders per Sprint 0.1's adopted architecture, `docs/DOMAIN_BLUEPRINT.md` §1).
- Architecture Decision Records (`docs/adr/`) backfilling the frozen decisions from the Domain Blueprint.
- Developer onboarding guide (`docs/developer/getting-started.md`).
- Repository governance: PR template, CODEOWNERS.
