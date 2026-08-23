# BUS-0031: Learning Eligibility Boundary

**Status:** 🟢 Accepted

**Date:** 2026-07-27

**Related Domains:** Students, Learning, Academic

**Related ADRs:** [ADR-0024](../0024-shared-role-pattern-actor-governed-resource.md) (technical track — Participation's Actor slot, unchanged by this decision), [BUS-0025](0025-hr-assignment-model.md) (the Position-Assignment-requires-active-Employment precedent this decision reuses)

## Context

Learning's certified Reference Blueprint states its Inputs as "Academic (Subject, optional Subject Offering), Students (Enrollment, Person), HR (Employee/Position)" without specifying which of Enrollment or Person is the operative reference for Course Offering Participation eligibility. Surfaced during Students Architecture Discovery (2026-07-27) as the most consequential open boundary question in that report: whether a withdrawn or graduated Person can remain, in effect, academically active within Learning.

## Problem Statement

When a Student participates in a Course Offering (an `ADR-0024` Participation instance), should eligibility be gated by Person alone, by Enrollment, or some combination — and how should this interact with withdrawal, graduation, suspension, branch transfer, and non-Student participants (external/corporate learners)?

## Decision

**Participation's `ADR-0024` Actor slot remains Person**, consistent with every other instance of the pattern on this platform (Employment, Position Assignment, Teacher Assignment) — this decision does not touch `ADR-0024` itself. **When the Actor is a Student, an active Enrollment becomes a required gating/validity reference for the Participation** — the same mechanism `BUS-0025` already established for Position Assignment requiring an active Employment: "a validation constraint, not structural containment." When the Actor is a non-Student participant (external or corporate learner, per the Architecture Assumption already recorded in `BUSINESS_BLUEPRINT.md`), no Enrollment gate applies, since none exists for that Actor.

This closes the specific failure mode Discovery flagged: a withdrawn or graduated Student's Enrollment closes or reaches a terminal state, and any Participation gated by it becomes invalid at that same moment — no separate eligibility check required.

## Alternatives Considered

- **Person only** — rejected. Cannot natively represent withdrawal, graduation, suspension, or branch transfer — a withdrawn Person's Course Offering Participation would remain silently valid unless Learning re-implemented Enrollment-state checking on its own, duplicating logic Enrollment already owns. Also loses the ability to distinguish separate enrollment periods for a Person who withdraws and later returns (frozen law's own reason for treating Enrollment as independent top-level identity applies here with equal force).
- **Enrollment only, replacing Person as the Actor** — rejected. Would make Learning's Participation the one instance of `ADR-0024` anchored on something other than Person, breaking consistency with every other instance of the pattern (Employment, Position Assignment, Teacher Assignment) and requiring `ADR-0024` itself to be reopened or special-cased — not attempted here.

## Trade-offs

Slightly more setup than a pure Person reference — Learning must resolve the Student's current/relevant Enrollment, not just Person, when the Actor is a Student. This is the same cost `BUS-0025` already accepted for Position Assignment↔Employment, not new complexity introduced here.

## Architectural Consequences

A Participation whose Actor is a Student now carries an explicit, required Enrollment reference as a gating condition; ending or invalidating that Enrollment (withdrawal, graduation, or the terminal state of a branch transfer's prior Enrollment) invalidates the dependent Participation without a separate rule. Suspension — a sub-status on the same Enrollment, not a new one, per frozen law — becomes visible to Learning through the same reference, without inventing a parallel status.

## Domain Impact

Learning's own Inputs line is clarified, not changed in substance: "Students (Enrollment — required gate when Actor is a Student; Person — the Actor itself)." Students' Domain Contracts gain a "Publishes: Enrollment state, consumed by Learning as a Participation validity gate" line. Academic is unaffected — its own Report Cards/Grades already key off Enrollment, and this decision brings Learning into alignment with that existing convention rather than introducing a new one.

## Integration Impact

No change for non-Student Learning participants (external/corporate) — they were never eligible for an Enrollment-based gate and remain ungated, exactly as before. Any future downstream integration reading Learning Participation for reporting or academic-integrity purposes can now rely on Enrollment state as the single source of truth for a Student's eligibility window, rather than re-deriving it.

## Validation

- No conflict with `ADR-0001` — Person identity untouched.
- No conflict with `ADR-0024` (technical track) — the Actor slot is unchanged; this decision adds a domain-specific validity constraint, the same extensibility point the pattern already provides (as used by `BUS-0025`).
- No conflict with the Academic Reference Blueprint — Report Cards/Grades already anchor to Enrollment; this decision brings Learning in line with, not against, that existing convention.
- No conflict with the Learning Reference Blueprint — its own stated Inputs already named both Enrollment and Person; this decision specifies which governs eligibility rather than contradicting either.
- No conflict with the HR Reference Blueprint — unrelated; HR has no relationship to Students (Students Discovery finding #9) and none is introduced here.

## Status

🟢 Accepted.
