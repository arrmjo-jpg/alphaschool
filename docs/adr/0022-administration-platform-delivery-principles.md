# ADR-0022: Administration Platform Delivery Principles

**Status:** Accepted

**Date:** 2026-07-14

## Context

ADR-0016 through ADR-0021 freeze the architecture. This ADR freezes the *binding* rules governing how it is built — sequencing, parallelization boundaries, and the point at which UI work may begin — as distinct from the concrete phase-by-phase schedule, which belongs in `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` and is expected to adapt as real sprints are scheduled, the same way the main Implementation Playbook's own Phase 5+ entries are deliberately left at "epic-level only" pending dedicated planning passes. The principles below are architectural, not scheduling convenience, and violating them risks exactly the kind of inconsistency this entire review exists to prevent.

## Decision

**1. The Configuration Platform (ADR-0018) and the Provider Registry (ADR-0019) must never be built in parallel.** Provider credentials are Altitude-scoped and approval-gateable using the Configuration Platform's own mechanisms directly. Building both simultaneously risks two teams independently inventing two altitude-resolution algorithms that quietly disagree — the single most concrete way this architecture could fail without anyone deciding it should.

**2. The Administration Platform data-boundary architecture test (ADR-0016 §5) must exist and be proven, via a deliberate negative case, before the first real Configuration Platform migration is written** — not retrofitted once real data exists. This mirrors the precedent already set for Core's temporal-pattern enforcement (Blueprint Addendum A3: "build the trait and its architecture tests together") and for Identity Maintenance's contract-declaration scanner (Sprint 3.1).

**3. A capability's backend must be frozen — tested, ADR-reviewed if it touches a new architectural boundary, tagged — before its Workspace UI construction begins**, with exactly one deliberate exception: the generic Configuration browser, which may be built in parallel with the Provider Registry's backend work, because it validates the *framework* (the already-proven `v1.0-admin-platform-foundation` shell, DataTable, and Form components) against a stable, already-frozen API (the Configuration Platform, Decision 1), not a business capability still in motion.

**4. The Administration Experience Layer (ADR-0021) must never be built ahead of the capabilities it derives from.** It has nothing to compute over until at least two real capabilities are live with real registered content; building it earlier is speculative and produces tooling nobody can evaluate against real complexity.

**5. Business Rules (ADR-0020) are never migrated into Configuration Platform storage as an implementation shortcut**, even temporarily, even under schedule pressure. This is named as the single highest-probability failure mode in this entire delivery plan (see Risks below) precisely because each individual instance of it looks reasonable in isolation.

**6. Independent modules with no shared schema may be built in parallel freely** — this is not a new principle, it is Blueprint §17's own parallelization philosophy ("Phase 5 onward opens up real parallelization... because the module-boundary architecture was designed to make this possible"), applied here to Administration Platform's own internal capability build-out exactly as it already applies to the wider ERP.

**7. A capability requiring its own dedicated design session (Digital Experience Delivery's Website surface, Asset & Facility Stewardship in full) must not begin implementation before that session concludes**, but the session itself — pure architecture discussion — may and should run concurrently with unrelated implementation work, since it consumes no shared engineering capacity.

## Consequences

These seven rules are the actual guardrails a future contributor, engineering manager, or AI agent must check before deviating from the Playbook's suggested sequence for any reason — including reasonable-sounding ones like "the customer needs Website sooner." Deviating from the *schedule* in `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` is a project-management decision. Deviating from any rule in this ADR is an architecture decision and requires the same review weight as any other ADR-level change.

## Alternatives Considered

- **Treating sequencing purely as a Playbook concern, with no binding ADR.** Rejected — several of these rules (1, 2, 5) protect architectural invariants established in ADR-0016/0018/0020, not merely delivery efficiency; if they are only ever written as scheduling advice, they will be the first things compressed under a deadline.

## References

`docs/adr/0016-administration-platform-data-boundary-and-philosophy.md` through `0021`. `docs/DOMAIN_BLUEPRINT.md` §17 (Recommended implementation order, parallelization philosophy), Addendum A3 (enforcement-mechanism sequencing), Addendum A10 (Core-first sequencing precedent). `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` (the concrete, adaptable phase schedule these principles bind).
