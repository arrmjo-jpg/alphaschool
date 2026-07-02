# ADR-0004: Enrollment Is a Separate Aggregate From Student

**Status:** Accepted

**Date:** 2026-07-01

## Context

A student's academic relationship to the school changes over time — promotion, repetition, branch transfer, suspension, graduation, withdrawal, and re-enrollment years later. Historical reports (attendance, grades, fees) generated years after the fact must remain accurate as of the period they describe, and repeating a grade must not conflate two different years' records under one grade label.

## Decision

`Student` stays a permanent, minimal identity anchor (`person_id`, `student_number`, coarse `lifecycle_status`). `Enrollment` is a separate aggregate root (`student_id`, `academic_year_id`, `branch_id`, `grade_level_id`, its own status machine, `previous_enrollment_id`/`next_enrollment_id` chain). A new Enrollment row is created whenever the branch/grade/academic-year envelope changes or the relationship ends; suspension is a sub-status on the same Enrollment, not a new row; section changes get a finer-grained sub-history below Enrollment. `students.current_enrollment_id` is a maintained pointer, never the source of truth.

## Consequences

Attendance, Grades, Fees, and Behavior records can reference a specific enrollment period unambiguously, so a repeated grade never conflates two years' data. Historical reports remain correct without ever needing to interpret an overwritten field. Cost: every "current state" query (e.g. "students in Branch X") must go through Enrollment, not a flat column on Student, and this superseded an earlier design that had put `branch_id` directly on Student.

## Alternatives Considered

- **Mutable `branch_id`/`grade_level_id`/`status` fields directly on Student, updated on each transition.** Rejected — this is exactly the "overwrite history" mistake the whole project's historical-integrity principle exists to prevent, and would make "what grade was this student in during 2023" require parsing an audit log instead of a plain query.

## References

`docs/DOMAIN_BLUEPRINT.md` §3, §9.
