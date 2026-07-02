# Changelog

All notable changes to AlphaSchool ERP are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Repository bootstrap: git monorepo initialized (`backend/`, `admin/`, `frontend/`).
- Engineering tooling: Pint, Larastan, deptrac, Pest (unit/feature/architecture suites).
- CI pipeline (GitHub Actions) for `backend/`, path-filtered, running the full quality-gate suite on every PR.
- `app/Core` and `app/Modules/*` skeleton (README-only placeholders per Sprint 0.1's adopted architecture, `docs/DOMAIN_BLUEPRINT.md` §1).
- Architecture Decision Records (`docs/adr/`) backfilling the frozen decisions from the Domain Blueprint.
- Developer onboarding guide (`docs/developer/getting-started.md`).
- Repository governance: PR template, CODEOWNERS.
