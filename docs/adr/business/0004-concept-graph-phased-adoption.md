# BUS-0004: Concept Graph Is Built as an Architectural Seam Now, Adopted as the Primary Substrate in a Later Phase

**Status:** 🔵 Deferred (seam accepted now; full adoption deferred, phased)

**Date:** 2026-07-22

**Related Domains:** Learning

**Related ADRs:** BUS-0001 (Course Template versioning — the version a Concept Graph tag eventually attaches to)

## Context

A Learning Objective Graph (a machine-readable, fine-grained concept/skill dependency graph, distinct from the coarser, credentialing-facing Competency) was proposed as the primary pedagogical substrate an AI-first Learning domain should be built on, with Course/Offering demoted to an administrative wrapper around it.

## Problem

Should the Concept Graph be the mandatory, primary content structure from v1, or introduced later?

## Alternatives Considered

- **Concept Graph as the mandatory v1 primitive** — the original proposal. Rejected: a teacher wanting to quickly stand up a course would be forced to build a full concept graph before writing a single lesson — unrealistic adoption friction for the actual, primary users of the system on day one.
- **No Concept Graph at all, defer indefinitely** — rejected: would foreclose real adaptive-learning capability later, and retrofitting a concept graph onto years of un-tagged content is a much larger migration than reserving the seam now.
- **Three-phase adoption: optional in v1, AI-assisted extraction in phase 2, primary substrate in phase 3** — accepted.

## Final Decision

Phase 1 (v1): Course → Units → Lessons, with an *optional* Learning Objective reference on each Lesson — implemented as an optional foreign key into a (mostly empty) Concept Graph node table from day one, not a placeholder free-text field to be migrated later. Phase 2: AI-assisted extraction of Concept Graph structure from existing, already-authored content. Phase 3: Concept Graph becomes the primary substrate driving Adaptive Learning.

## Why This Decision Was Chosen

Matches a pattern already proven in this exact project: `Branch.parent_branch_id` was added as "a cheap, nullable seam for a future... layer, deliberately not required this sprint... without a structural migration later." The same move — reserve the real shape now, require nothing of it yet — avoids both the adoption-friction failure of mandatory day-one complexity and the technical-debt failure of a placeholder field that needs replacing later.

## Consequences

Easier: v1 ships with a familiar, low-friction authoring experience; the eventual Concept Graph requires no data migration, only backfilling an already-correctly-shaped optional field. Harder: Phase 2 (AI-assisted extraction) is now load-bearing, not optional-nice-to-have — see the flagged risk below.

## Future Implications

**Named risk, not resolved by this decision alone:** if Phase 1's optional field goes mostly unused (the realistic default outcome of any optional field), Phase 2's AI-assisted extraction is the only thing that prevents Phase 3's Adaptive Learning promise from being hollow for most content. Phase 2 must be built with genuine rigor, not treated as a minor enhancement.

## Traceability

- **Business requirement:** "AI as a first-class capability" balanced against realistic teacher adoption in v1.
- **Introduced in:** the "Forget Moodle, Forget Canvas" AI-first entities discussion; phased plan proposed and accepted in the immediate follow-up turn.
- **Depended on by:** BUS-0001; any future Adaptive Learning feature.
