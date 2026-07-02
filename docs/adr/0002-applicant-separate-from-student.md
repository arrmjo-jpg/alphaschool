# ADR-0002: Applicant Is a Separate Aggregate From Student

**Status:** Accepted

**Date:** 2026-07-01

## Context

Online admissions require a multi-stage review (submission, testing, decision, payment) before a child becomes an enrolled student. A significant fraction of applicants are accepted but never pay, or withdraw before enrolling.

## Decision

`Applicant` is its own aggregate root (own status machine: submitted → under_review → tested → accepted/rejected → payment_pending → paid → converted/withdrawn/expired), referencing `Person` directly. A `Student` row is created only once, by a synchronous `ConvertApplicantToStudentAction`, after successful payment. The Applicant row is retained permanently, even on rejection, linked to the resulting Student via `applicant_id` for traceability.

## Consequences

A `Student` row always means "a real, currently-or-formerly-enrolled child" — no report, permission check, or downstream module ever has to account for a Student that might not really be enrolled. Admissions funnel analytics and re-application detection come for free from the retained Applicant history. Cost: Admissions must orchestrate creating both Student and its first Enrollment atomically, and Application Number and Student Number must be kept as deliberately separate identifier spaces.

## Alternatives Considered

- **A `status` column on Student including `applicant`/`accepted` values.** Rejected — this is the aggregate-invariant-erosion mistake: a Student row would sometimes mean "real student" and sometimes mean "maybe never enrolls," breaking every other module's assumption about what a Student row is.

## References

`docs/DOMAIN_BLUEPRINT.md` §3, §9; Addendum A1 (Admissions/Academic sequencing correction).
