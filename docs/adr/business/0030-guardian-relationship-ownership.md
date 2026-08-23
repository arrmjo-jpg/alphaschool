# BUS-0030: Guardian Relationship Ownership

**Status:** 🟢 Accepted

**Date:** 2026-07-27

**Related Domains:** Students, Parents/Family (future)

**Related ADRs:** [ADR-0024](../0024-shared-role-pattern-actor-governed-resource.md) (technical track — its own Out of Scope section is the reason Option C below is foreclosed, not reopened)

## Context

`students.md` (v1) contained a self-contradiction: its Responsibilities line stated guardian relationship links are "consumed from People/Family, not owned here," while its own Submodules line listed "Guardian Links" as a Students-owned submodule. Frozen technical architecture (`DOMAIN_BLUEPRINT.md`) states `guardian_student` is "not owned by either side — a first-class relationship record," but never assigns ownership to a specific business domain. A "Parents" domain already sits reserved, undocumented, in `BUSINESS_BLUEPRINT.md`'s backlog. Surfaced during Students Architecture Discovery (2026-07-27) as a genuine, unresolved boundary question — not decided at Discovery stage, deferred to a dedicated Architecture Review.

## Problem Statement

Which business domain owns Guardian (as a relationship context) and the `guardian_student` join — Students, a future Parents/Family domain, or a shared `ADR-0024`-pattern instance?

## Decision

**A future Parents/Family domain owns Guardian and `guardian_student`.** Students consumes the relationship — the same cross-reference discipline already used for Academic Department↔HR Department (`BUS-0017`/`BUS-0024`) — never owns it. This is a seam reservation: the Parents/Family domain doesn't need to be built now, but the ownership boundary is settled today so no other domain (Students least of all) accidentally becomes a de facto second owner in the meantime.

## Alternatives Considered

- **Students owns the relationship** — rejected. `guardian_student` is a durable Person-to-Person relationship (survives grade promotions, and a Withdrawal→Return chain per frozen law) while Enrollment is a period-scoped record; forcing the former into the latter's machinery repeats the exact lifecycle-shape mismatch already corrected elsewhere on this platform (Contract vs. Employment, `BUS-0027`; Position Assignment vs. Employment, `BUS-0025`). Would also require an ownership migration later once a Parents/Family domain is eventually built — the kind of rework this platform's seam-reservation discipline (Provider Slots, Registry Pattern) exists specifically to avoid.
- **`ADR-0024` Relationship Pattern instance** — **foreclosed, not merely rejected.** `ADR-0024`'s own Out of Scope section, established during that ADR's original stress test, explicitly names Guardian/Parent as a real counterexample ("Person-to-Person, not Person-to-Resource"). Modeling `guardian_student` as an `ADR-0024` instance now would require reopening an Accepted ADR, which this decision does not do.

## Trade-offs

The Student & Parent App (named in `students.md` as Students' primary surface) will aggregate two domains — Students and the future Parents/Family domain — rather than being single-domain-owned as v1 currently implies. This is the same aggregation shape HR's own Dashboard already uses across multiple domains; not a new pattern.

## Architectural Consequences

`students.md`'s "Guardian Links" Submodule entry is dropped; its Responsibilities line ("consumed from People/Family, not owned here") was already correct and stands unchanged. Students' Configuration item "default guardian-access permissions" is rescoped from owned-here to consumed-from-Parents/Family (knock-on effect, Students Discovery item #14). No schema or technical change — `guardian_student` already exists as an unowned join at the technical layer; this decision only assigns its business-domain ownership.

## Domain Impact

Students' Domain Contracts gain an explicit "Never owns: Guardian, `guardian_student`" line. The future Parents/Family domain, whenever documented, inherits ownership of Guardian-context, `guardian_student`, and guardian-access permission rules as pre-settled, not open, questions.

## Integration Impact

Reception's existing Parent App surface (visitor pre-registration) integrates with the future Parents/Family domain, not Students, for guardian identity. Notification routing that resolves "who are this Student's guardians" reads `guardian_student` as a cross-reference, regardless of which domain eventually implements it — no consumer-facing change.

## Validation

- No conflict with `ADR-0001` — Person/Guardian context untouched.
- No conflict with `ADR-0024` (technical track) — Guardian/Parent was already, and remains, explicitly out of that pattern's scope.
- No conflict with the Academic, Learning, or HR Reference Blueprints — none claim any ownership over Guardian or `guardian_student`.
- No conflict with frozen `DOMAIN_BLUEPRINT.md` — "not owned by either side" is honored, not contradicted; this decision specifies who eventually does own it, filling a gap frozen law deliberately left open rather than overriding anything it settled.

## Status

🟢 Accepted.
