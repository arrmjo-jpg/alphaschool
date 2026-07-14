# Administration

**Layer:** Foundation

Owns exactly four schema shapes, and nothing else: the Configuration Registry (declared settings schema + resolved values), the Provider Registry and Credential Vault, Package/Snapshot artifacts, and the Experience Layer's derived, rebuildable compilations (e.g. the Dependency Graph). Never Content, never Reference/Master Data, never Business Rules — those stay owned by whichever Domain or Foundation module already governs them, merely administered here, never re-implemented.

Formerly named `Settings` (Sprint 0.1 placeholder, matching `docs/DOMAIN_BLUEPRINT.md` §1's original Foundation-module table before `docs/adr/0011-administration-platform-bounded-context.md` absorbed that charter and `docs/adr/0016-administration-platform-data-boundary-and-philosophy.md` retired the word "Settings" from the architecture vocabulary entirely).

See `docs/ADMINISTRATION_PLATFORM.md` (Blueprint), `docs/adr/0016` through `0022` (architecture), `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` (execution — this module's own boundary is enforced by `tests/Architecture/AdministrationPlatformBoundaryTest.php`, written and proven in Phase 0 before any real Configuration Platform migration exists).

Populated starting Phase 1 (Configuration Platform Core) — empty as of Phase 0 (placeholder only, no schema yet).
