# BUS-0025: HR Assignment Model

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR, Academic (BUS-0019, the pattern it claims to reuse), Learning (staffing Participation consumption)

**Related ADRs:** `ADR-0024` (technical track — Actor↔Governed-Resource pattern, this ADR is a direct confirming instance), BUS-0019 (Academic Assignment Model, the peer this ADR makes factually consistent), BUS-0024 (Organizational Structure, defines the Position this ADR assigns Persons to)

## Context

BUS-0019 states Academic's Teacher/Homeroom/Coordinator Assignment "reuses HR's own Position/Role/Assignment pattern" — but HR never defined that pattern itself, leaving the claim unverifiable. The Assignment Model Review evaluated whether Employment and Position Assignment are the same concept or two, against `ADR-0024`.

## Problem Statement

Does Employment directly own Position (a mutable field), or does Employment relate to one or more effective-dated Position Assignments as a distinct concept?

## Decision

Employment and Position Assignment are **two different, related `ADR-0024` instances, not one containing the other**:

- **Employment** = Person ↔ Organization/Branch, Capacity = "Employee" — the continuous fact of being employed at all.
- **Position Assignment** = Person ↔ Position, Capacity = the specific Role held — effective-dated, multi-cardinality-capable.

A valid Position Assignment requires an active Employment — a validation constraint, not structural containment.

## Alternatives Considered

- **Employment owns Position directly** (a mutable field) — rejected. Fails history (silent overwrite, or Employment fragmented into separate records per Position held), fails transfers and promotions cleanly, cannot express acting assignments, cannot express concurrent positions, and directly contradicts BUS-0019's own claim — there would be no pattern for Academic to reuse.
- **A structurally different third model** — evaluated, not found. The only genuine alternative considered (versioning Position itself per-holder) fragments Position's own stable identity and is strictly worse than the accepted option.

## Trade-offs

The accepted model requires Position Assignment as its own record rather than a field — marginally more structure — in exchange for full history, transfer/promotion/acting-assignment support, and concurrent-position capability, none of which the rejected alternative could provide at all.

## Architectural Consequences

Transfers and promotions become "end one Assignment, start another" — no new mechanism. Acting assignments become a new Capacity value on a concurrent Assignment — no new mechanism. Full as-of-date history becomes queryable, the same `asOf(date)` idiom already proven for Academic's own Assignment pattern.

## Domain Impact

HR's Employees workspace displays current and historical Position Assignments natively — no separate "Assignments" workspace section, consistent with HR Workspace Architecture's own explicit requirement that Position Assignment stay a mechanism, not a destination.

## Integration Impact

Academic's BUS-0019 claim is now factually grounded, not aspirational. Future Payroll can consume Assignment history directly for compensation-period reconstruction. Learning's consumption of HR's Employee/Position is unaffected and now more precisely specified.

## Validation

- No conflict with `ADR-0001` — Person remains the Actor substrate, unaffected.
- No conflict with `ADR-0024` (technical track) — this ADR *is* a direct, confirming application of it, exactly the shape `ADR-0024`'s own Consequences section already predicted for Employment.
- No conflict with the Academic Blueprint — BUS-0019's Assignment Engine pattern and this ADR's Position Assignment are now confirmed peers, not competitors.
- No conflict with the Learning Blueprint — Learning's staffing Participation already assumed this shape.
- No conflict with HR Workspace Architecture — that document required Position Assignment to remain a mechanism, not a section; this ADR is what makes that requirement true rather than assumed.

## Status

🟢 Accepted.
