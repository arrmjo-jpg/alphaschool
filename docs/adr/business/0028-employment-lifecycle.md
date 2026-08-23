# BUS-0028: Employment Lifecycle

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR

**Related ADRs:** BUS-0025 (Employment, the entity this lifecycle governs), BUS-0027 (Contract, referenced by but distinct from lifecycle state)

## Context

hr.md names only "Hire" (opens) and "Termination/Resignation" (closes) as Employment states, despite "probation period length" already existing as a Configuration item with no stated state for it to gate.

## Problem Statement

What is Employment's full lifecycle state machine, and what triggers each transition?

## Decision

Employment carries the states: **Probation → Active → (optionally) On Leave → Terminated / Resigned / Retired.**

- **Probation** is a real, distinct state, gated by the existing "probation period length" Configuration value, ending in either confirmation (→ Active) or non-confirmation (→ Terminated, tracked distinctly from ordinary termination for reporting purposes).
- **On Leave** is a temporary state, not a termination — an approved Leave Request (HR Workspace Architecture's Leave section) may place Employment into On Leave without closing it.
- **Retirement** is its own terminal state, distinct from ordinary Termination/Resignation, since it carries different downstream implications (pension/benefits) even though it structurally closes Employment the same way.

## Alternatives Considered

- **Binary open/closed only** — rejected. Cannot express what "probation period length" actually gates, and cannot express On Leave without either incorrectly closing Employment or losing the information entirely.
- **A generic, domain-agnostic status field with no defined transitions** — rejected. Enrollment's own richer state machine (promote/repeat/transfer/withdraw/graduate) is the direct precedent for a real, named state machine, not an open-ended field — the same "don't invent a second, worse implementation of an already-proven pattern" discipline applied elsewhere.

## Trade-offs

A richer state machine requires more upfront definition than binary open/closed, in exchange for actually supporting what's already implied elsewhere in this domain (probation, leave interaction) rather than leaving those implications unaddressed.

## Architectural Consequences

Domain Events become precise: `EmploymentStarted` (Probation), `ProbationConfirmed` / `ProbationFailed`, `EmploymentSuspended` / `EmploymentResumed` (Leave interaction), `EmploymentTerminated` / `EmploymentResigned` / `EmploymentRetired`.

## Domain Impact

The Employees workspace section now has a concrete state machine to display and act on, rather than an implied binary.

## Integration Impact

Future Payroll can key compensation/benefit rules directly off Employment state (benefits eligibility commonly differs between Probation and Active). Leave's own approval workflow gains a defined interaction point with Employment state.

## Validation

- No conflict with `ADR-0001`.
- No conflict with `ADR-0024` (technical track) — Employment's lifecycle *state* is internal detail of the Employment instance; the pattern's own generic "lifecycle state" field already anticipates domain-specific state values.
- No conflict with the Academic or Learning Blueprints — neither depends on Employment's internal states.
- No conflict with HR Workspace Architecture — the Employees section was already scoped to own this.

## Status

🟢 Accepted.
