# ADR-0001: Person as the Identity Substrate

**Status:** Accepted

**Date:** 2026-07-01

## Context

`User` was defined as authentication-only (username/email/phone/password/status). The system needs to represent Students, Employees, and Guardians, and a single physical person can hold more than one of these roles simultaneously (e.g. a teacher whose own child is enrolled as a student). A design that ties account classification directly to `users` cannot represent this without either duplicate accounts or a fragile multi-column workaround.

## Decision

Introduce `Person` as a standalone identity aggregate, owning biographical identity (bilingual name parts, DOB, gender, nationality, photo). `User`, `Employee`, `Student`, `Guardian`, and `Applicant` each reference `Person` by `person_id`. `User.person_id` is nullable and one-way — User never gains a direct FK to any context aggregate. Account type (which portal a login can reach) is derived from which context rows exist for a Person, never stored as an enum.

## Consequences

Every subsequent design decision in this project (Family, Admissions→Student conversion, Enrollment, Employment, Identity Maintenance) was able to build on this substrate without redesigning identity. A person leaving as a student and later returning as an employee, or holding both roles at once, requires no special-casing. The cost: every context aggregate now needs to look up through Person rather than owning its own name/DOB columns, and duplicate-detection (fuzzy name/DOB matching) becomes necessary at every registration point to avoid creating a second Person for someone who already exists.

## Alternatives Considered

- **A single `account_type` enum on `users`.** Rejected — cannot represent one physical person holding two account types simultaneously without duplicate accounts.
- **Separate, unrelated tables per role with no shared identity concept.** Rejected — makes "is this the same person" undecidable and blocks the sibling/family data-reuse goal from day one.

## References

`docs/DOMAIN_BLUEPRINT.md` §3, §8.
