# ADR-0009: Identity Maintenance / Module Contract Boundary

**Status:** Accepted

**Date:** 2026-07-06

## Context

Addendum C3 established that every Person-referencing module implements `ReassignsIdentityReferences`/`RedactsPersonalData`, and Person (Sprint 2.1), then Employee/Student/Guardian (Sprint 2.4), all implemented them trivially — an unconditional data move, a no-op redaction. Sprint 2.4's implementation review surfaced two questions the Addendum never answered explicitly: who is responsible for validating that a reassignment is *safe* to perform (e.g., both the losing and winning Person each already holding a row in a uniquely-constrained table), and who is responsible for ensuring a reassignment is never executed twice for the same merge, given that queue retries, worker crashes, duplicate events, and manual retries are all realistic occurrences over a multi-year system lifetime. Left unanswered, each future module implementing these contracts would have to independently guess, and different modules guessing differently is exactly how a supposedly-uniform contract quietly stops being uniform.

## Decision

The validation and idempotency responsibilities belong entirely to Identity Maintenance (the orchestrator, Phase 3), never to the individual module implementing the contract. Concretely:

1. **Structural-conflict validation is Identity Maintenance's job.** Before calling any module's `reassignPerson()`, Identity Maintenance must confirm the operation won't violate that module's own known constraints (e.g., a unique `person_id` — both Employee, Student, and Guardian each enforce this today). A module's `reassignPerson()` implementation is entitled to assume this has already been checked; it must never defensively re-implement the check itself, since it only has visibility into its own table, not the merge as a whole.
2. **Idempotency is Identity Maintenance's job (Guarantee #7, added to the standing list Identity Maintenance owes every implementing module).** Identity Maintenance must guarantee a reassignment operation cannot execute twice for the same merge request, regardless of queue retry, worker crash, duplicate event, or manual retry. Modules implementing `ReassignsIdentityReferences` may assume they are called at most once for a successful merge, and do not need to build their own duplicate-call detection.
3. The full guarantee list Identity Maintenance owes every implementing module: pre-validated structural safety; atomicity (all reassignments for one Merge in a single transaction); complete and correct registration (every declared module is called, an undeclared one never is); approval already granted (Addendum C10 — no self-approval, even for Super Admin); domain-specific vetoes already cleared (Addendum C9's future `canReassignPerson()`); logging ownership (`merge_reassignment_log`, Addendum C8, is Identity Maintenance's to write, not the module's); and idempotency (Guarantee #7, above).

## Consequences

Every module implementing these contracts (Person, Employee, Student, Guardian today; every future Person-referencing module thereafter) can stay genuinely dumb — a plain, unconditional operation, with zero internal validation or duplicate-call logic. This keeps each implementation trivial to write and trivially reviewable, and concentrates all the hard, cross-cutting correctness problems (structural validity, atomicity, exactly-once execution) in the one component built specifically to reason about a merge as a whole. Cost: none of this is enforced by any real caller today — Identity Maintenance doesn't exist until Phase 3 — so this ADR documents a contract Phase 3 must honor when built, not an active guarantee in the system as it stands.

One incidental, non-relied-upon fact worth recording: today's specific `reassignPerson()` implementations (a plain `UPDATE ... WHERE person_id = oldId`) happen to be idempotent at the SQL level even without Identity Maintenance's guarantee, since a second call finds no matching rows. This must never be treated as a substitute for Guarantee #7 — a future module's implementation (e.g., one that appends a log row per call rather than performing a conditional update) would not share this accidental property, and the contract must not depend on every implementer being safe by luck.

## Alternatives Considered

- **Each module implements its own defensive validation and duplicate-call detection.** Rejected — requires every module to have visibility into the state of a merge spanning a different Person's records, which a single module's own table cannot provide; also duplicates the same logic across every future implementer, each an independent chance to get it wrong.
- **Rely on `reassignPerson()`'s implementations happening to be naturally idempotent (as today's `UPDATE`-based ones are).** Rejected — this is a property of the current, specific implementations, not a guarantee of the contract itself; a future module's implementation is not guaranteed to share it.

## References

`docs/DOMAIN_BLUEPRINT.md` Addendum C (C3, C8, C9, C10, C11). `docs/developer/people-context-shells.md`. Raised during Sprint 2.4's Step 1 implementation review, formalized before Step 4's readiness check.
