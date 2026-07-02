# ADR-0005: Employment Is a Separate Concept From Employee

**Status:** Accepted

**Date:** 2026-07-01

## Context

An employee can resign and be rehired years later. Position and Salary history were initially designed as direct children of the Employee aggregate, but this offers no clean way to disambiguate two separate employment stints, and pension/tenure/severance calculations often depend on continuous years within *one* employment period, not lifetime-cumulative time.

## Decision

Mirror ADR-0004's Enrollment/Student split: `Employee` stays the permanent identity anchor; `Employment` is a separate concept representing one hire-to-termination period, and it is Employment — not Employee directly — that owns Position history, Salary history, and branch membership for that period. A rehire opens a new Employment period, chained to the previous one.

## Consequences

Position/Salary/branch-membership history is unambiguously scoped per employment stint, and tenure-dependent calculations (severance, benefits eligibility) have a clean boundary to reference. This corrects an earlier statement in the Domain Blueprint (originally: Position/Salary as direct Employee children) — implementers should build against this ADR, not the superseded text.

## Alternatives Considered

- **Position/Salary history as direct Employee children with gaps for resignation periods.** Rejected once the parallel to Enrollment/Student was recognized — gaps in a flat history don't give tenure calculations a clean period boundary the way a distinct Employment aggregate does.

## References

`docs/DOMAIN_BLUEPRINT.md` §3, §4, §10, Addendum B2.
