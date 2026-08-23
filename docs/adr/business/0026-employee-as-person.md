# BUS-0026: Employee as a Person Specialization

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR

**Related ADRs:** `ADR-0001` (Person as identity substrate — the pattern this directly applies)

## Context

hr.md never explicitly stated Employee's relationship to the platform's identity substrate, though the answer is already implied by precedent (Applicant, Student).

## Problem Statement

Is Employee a Person-specialization, or an independent identity record?

## Decision

Employee is a Person-specialization, per `ADR-0001`. Employee = Person + an active or historical Employment relationship — the same shape already used for Student (Person + Enrollment) and Applicant (a Person who may never become anything else).

## Alternatives Considered

- **An independent Employee identity system, decoupled from Person** — rejected. Would duplicate identity data already captured once, and would be the only such anomaly on a platform that otherwise uses Person as its universal substrate without exception.

## Trade-offs

None of substance — this is a confirming decision, not a genuine trade-off between comparably strong options.

## Architectural Consequences

An external Instructor, Guest Lecturer, or future non-Employee Participant (per Learning's own model) can become an Employee later, or vice versa, without any identity migration — both are simply different relationships the same Person record can hold over time.

## Domain Impact

HR never owns identity itself, only the Employment relationship layered on top of it.

## Integration Impact

Consistent with Reception's Visitor precedent (a deliberate *exception* to Person, not the rule — Visitor was kept outside Person specifically because its lifecycle is temporary in a way Employee's isn't) and with every other Person-specialization already on the platform.

## Validation

- No conflict with `ADR-0001` — this is a direct application of it.
- No conflict with `ADR-0024` (technical track) — Person remains the Actor; unaffected.
- No conflict with the Academic or Learning Blueprints — both already assume Employee resolves through Person for their own staffing consumption.
- No conflict with HR Workspace Architecture — the Employees section's ownership (Employee, Employment) is unaffected by clarifying what Employee sits on top of.

## Status

🟢 Accepted.
