# ADR-0010: Student.lifecycle_status vs. Enrollment.status Boundary

**Status:** Accepted

**Date:** 2026-07-06

## Context

ADR-0004 established that Student stays a permanent, minimal identity anchor while Enrollment owns the academic lifecycle, but never specified precisely which events are allowed to touch which model's status field. Built without this boundary stated explicitly, Student's coarse `lifecycle_status` and Enrollment's future fine-grained status machine risk silently drifting out of sync the first time someone updates one without the other, or risk Student's enum being widened piecemeal (a `suspended` value here, a `promoted` value there) until it duplicates Enrollment's own richness — the exact "overwrite/duplicate history" failure ADR-0004 was written to prevent in the first place.

## Decision

`Student.lifecycle_status` answers exactly one question: does this Person currently have some active enrollment at all, in the broadest sense — in or out. It is a derived mirror, never independently editable by direct action. Only four events may ever change it: first admission (creates the Student row itself, `active`); graduation of the student's current/last Enrollment with no further enrollment following (`graduated`); withdrawal of the current/only active Enrollment with no immediately-linked re-enrollment (`withdrawn`); re-admission, a new Enrollment created for a previously withdrawn Student (`active` again).

Every other event touches only `Enrollment.status`, never Student: promotion, repetition, transfer between branches, academic suspension (a sub-status on the *same* Enrollment row per ADR-0004, not even a new row), section reassignment, and academic-year rollover for a continuing student. Transfer and withdrawal must be distinct terminal values on Enrollment (`transferred` vs. `withdrawn`) specifically so the Student-level rule stays simple: react to `withdrawn` and `graduated`, ignore everything else including `suspended` and `transferred`.

Suspension in particular will never become a fourth value on `Student.lifecycle_status`, regardless of whether the business wants it surfaced prominently (report cards, parent portal) — that visibility, if wanted, is a read-model concern reading `Enrollment.status` directly, not a reason to widen Student's own enum.

## Consequences

Student stays untouched across dozens of Enrollment transitions over a full school career — promotion every year, a repetition, a transfer, even a withdrawal-and-return cycle — exactly the property ADR-0004 already argued for. Phase 4 does not need to redesign this boundary; it needs to build the *mechanism* (whatever service or event listener updates `Student.lifecycle_status` and the future `current_enrollment_id` pointer together, transactionally, when one of the four boundary events fires) against a rule that is now fully specified. `students.current_enrollment_id` (ADR-0004's maintained pointer, built alongside Enrollment in Phase 4) needs the same transactional-side-effect treatment as `lifecycle_status`, updated on every Enrollment-creating event, not only the four that also touch `lifecycle_status`.

## Alternatives Considered

- **Let `Student.lifecycle_status` be independently editable by an admin action, separate from Enrollment transitions.** Rejected — creates two sources of truth for the same fact with no reconciliation mechanism, the precise drift risk this ADR exists to close off.
- **Add `suspended` (and other Enrollment-level nuances) as additional `Student.lifecycle_status` values to make suspension visible without a separate read-model.** Rejected — collapses the fine/coarse distinction ADR-0004 already established, and re-opens the door to Student's enum eventually duplicating Enrollment's own status machine piece by piece.
- **Treat branch transfer and withdrawal as the same Enrollment status value, distinguished by other means.** Rejected — would force the Student-level synchronization rule to inspect additional context on every transition instead of reacting to the status value alone, adding complexity to the one part of this system meant to stay simple.

## References

`docs/DOMAIN_BLUEPRINT.md` §3, §9, ADR-0004. `docs/developer/people-context-shells.md`. Raised during Sprint 2.4's Step 2 approval review.
