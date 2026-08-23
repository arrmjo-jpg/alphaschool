# BUS-0029: Position Lifecycle

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR

**Related ADRs:** BUS-0024 (Organizational Structure, defines Position itself), BUS-0025 (Assignment Model — establishes that "Filled" is derived, not stored)

## Context

Position was previously undiscussed beyond being static Master Data. The Assignment Model Review (BUS-0025) established that "vacant" is cleanly derivable as "no active Position Assignment references this Position" — but whether Position needs *additional*, independently-stored states, and how Recruitment anchors to it, was left open.

## Problem Statement

Does Position need its own lifecycle states beyond derived vacancy, and how does Recruitment anchor to a Position before or during a vacancy?

## Decision

Position carries states **Open (actively recruitable) → Filled (derived, not stored — has an active Assignment) → Frozen (temporarily not recruitable, budget hold) → Eliminated (non-destructive, retired per the platform's standard discipline).**

"Filled" restates the already-established BUS-0025 derivation, not a new stored value. Open, Frozen, and Eliminated *are* independently stored, since none can be derived from Assignment data alone — a Position can simultaneously be vacant and either Open, Frozen, or Eliminated, three distinct business meanings "no current Assignment" alone cannot distinguish. Recruitment anchors its Job Posting to a Position already in the Open state; a Position cannot be posted while Frozen or Eliminated.

## Alternatives Considered

- **No independent Position states, "vacant" alone drives everything** — rejected. Cannot distinguish "actively trying to fill this" from "on budget hold" from "no longer exists" — all real, distinct operational facts Recruitment and Organization Structure both need.
- **A separate "Vacancy" entity, independent of Position** — evaluated as a live option in the original Discovery Report; not adopted here. The state machine above provides what a separate Vacancy entity would have, without the added structural weight of a new top-level object.

## Trade-offs

Storing three explicit states plus one derived one is marginally more than pure derivation, but is the minimum needed to support Recruitment's own already-named workflow without inventing a separate Vacancy aggregate.

## Architectural Consequences

`PositionOpened`, `PositionFrozen`, `PositionEliminated` become real Domain Events. `PositionFilled` is explicitly not a stored-state event, but a read derived from Assignment's own events (BUS-0025).

## Domain Impact

The Organization Structure workspace section gains a real state model to display, and the "vacant Position count" KPI (HR Workspace Architecture) is now precisely defined as Open positions with no active Assignment.

## Integration Impact

Recruitment's Job Posting creation now has an explicit precondition (Position must be Open) rather than an implicit assumption.

## Validation

- No conflict with `ADR-0001`.
- No conflict with `ADR-0024` (technical track) — Position remains a Governed Resource; its own internal state doesn't alter the Assignment pattern referencing it.
- No conflict with the Academic or Learning Blueprints — neither references Position's internal state.
- No conflict with HR Workspace Architecture — the Recruitment and Organization Structure sections' KPIs/dependencies already anticipated exactly this.

## Status

🟢 Accepted.
