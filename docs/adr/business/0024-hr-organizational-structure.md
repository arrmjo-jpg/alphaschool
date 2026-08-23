# BUS-0024: HR Organizational Structure

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR, Academic (Department cross-reference, BUS-0017)

**Related ADRs:** BUS-0009 (default-with-override shape reused for reporting line), BUS-0017 (the precedent this cross-references, and the reason Department's own hierarchy must not disturb it)

## Context

The HR Architecture Discovery found Department and Position described only as isolated Master Data facts, with no stated relationship between Organization, Branch, Department, and Position, and no answer to whether reporting line derives from Department hierarchy or is modeled independently. The Organization Structure Review evaluated this directly.

## Problem Statement

How do Organization, Branch, Department, and Position relate to each other, and is reporting line derived from Department hierarchy or modeled independently?

## Decision

- **Department's Branch reference is nullable** — populated for branch-specific Departments, null for centralized/Organization-wide ones (Finance, HR itself).
- **Department is self-referential** (`parent_department_id`), the same shape already proven for Branch, Program, and Curriculum Path.
- **Position belongs to exactly one Department.**
- **Position carries its own self-referential "reports to" reference**, defaulting to the Department hierarchy's own head, explicitly overridable per-Position where it genuinely diverges (matrix/cross-functional structures) — the same default-with-override shape already proven for Tracking Strategy (BUS-0009) and the catalog-year lock (BUS-0017).
- **Department follows the platform's standard non-destructive lifecycle** — never hard-deleted, only marked Inactive.

## Alternatives Considered

- **Mandatory Branch scope for every Department** — rejected; forces every deployment to model centralized functions as artificially branch-owned.
- **Flat Department list, no hierarchy** — rejected; insufficient for any organization with real sub-departments.
- **Reporting line strictly derived from Department hierarchy, no override** — rejected; fails matrix organizations and cross-department reporting.
- **Reporting line as a fully independent, mandatory graph for every deployment** — rejected; over-engineers the common case where reporting already aligns with Department structure.

## Trade-offs

The default-with-override shape costs one additional field over a purely-derived model, in exchange for avoiding both the "too rigid" and "too heavy" extremes — small deployments never touch the override; complex ones use it exactly where needed.

## Architectural Consequences

Position becomes queryable both by Department membership and by reporting line independently, without two competing data models. The Organization Structure workspace can display either view natively.

## Domain Impact

HR's Department and Position now have a stable, precise shape other domains and future HR sub-decisions (Assignment, Recruitment) can safely build against.

## Integration Impact

Academic Department's cross-reference to HR's Department (BUS-0017) is unaffected — the cross-referenced target's internal shape changing to hierarchical does not alter the cross-reference contract itself.

## Validation

- No conflict with `ADR-0001` — Person is untouched by this decision.
- No conflict with `ADR-0024` (technical track) — this ADR defines the Governed Resources (Department, Position) that BUS-0025's Assignment model references; consistent, not conflicting.
- No conflict with the Academic Blueprint — BUS-0017's cross-reference to HR's Department remains valid; Academic Department's own hierarchy is separate and unaffected.
- No conflict with the Learning Blueprint — no direct reference to Department/Position internals exists there.
- No conflict with HR Workspace Architecture — the Organization Structure section was already scoped to exactly this content.

## Status

🟢 Accepted.
