# BUS-0027: Contract Model

**Status:** 🟢 Accepted

**Date:** 2026-07-26

**Related Domains:** HR

**Related ADRs:** BUS-0001 (Course Template versioning — the identity-vs-versioned-content pattern this reuses), BUS-0017 (Curriculum Path/Curriculum Specification — the same pattern's other proven instance), BUS-0025 (Employment, the identity this ADR's versioned content attaches to)

## Context

"Contracts" is named as a Submodule throughout HR's history but was never defined as its own Business Object — flagged as an open question in the original HR review, the HR migration, and the Discovery Report, unresolved until now.

## Problem Statement

Is Contract the same entity as Employment, or a distinct, versioned document attached to Employment?

## Decision

Contract is a **distinct, versioned document attached to Employment** — not Employment itself. Employment remains the continuous, effective-dated relationship (BUS-0025); Contract represents a specific signed agreement (type — temporary/permanent/part-time —, terms, dates) that can be renewed or change type without ending or fragmenting the underlying Employment. This reuses the same identity-vs-versioned-content pattern already proven for Subject/Subject Version and Curriculum Path/Curriculum Specification: Employment is the identity/continuity, Contract is the versioned terms document sitting on top of it.

## Alternatives Considered

- **Contract = Employment (same entity)** — rejected. Cannot express a contract-type change (temporary to permanent) without either ending Employment (breaking service continuity, tenure, benefits eligibility) or silently overwriting contract terms in place — both contradict the platform's non-destructive-history discipline.
- **Contract as a fully independent entity, only loosely linked to Employment** — rejected. Risks two aggregates independently managing the same real-world fact, the exact duplication already corrected elsewhere on this platform (Custody vs. Assets; Employment vs. Position Assignment itself, BUS-0025).

## Trade-offs

The accepted model requires one additional entity over the simplest option, in exchange for correctly representing contract-type transitions without breaking employment continuity — a real, common HR/legal need this domain cannot ignore.

## Architectural Consequences

A Contract renewal or type change becomes "publish a new Contract version," never an in-place edit or a break in Employment — consistent with every other versioned entity on this platform.

## Domain Impact

HR's Contracts workspace section now has a real shape to build against, rather than an empty placeholder.

## Integration Impact

None identified — Contract has no external consumers documented elsewhere on the platform at this time.

## Validation

- No conflict with `ADR-0001` — Person/Employee untouched.
- No conflict with `ADR-0024` (technical track) — Contract is not itself an Actor↔Governed-Resource relationship; it's Employment-referencing content, correctly excluded from that pattern, the same way Grades/Progress are correctly excluded from it in Learning.
- No conflict with the Academic or Learning Blueprints — neither references Contract.
- No conflict with HR Workspace Architecture — the Contracts section's location was already settled there; this ADR settles its content.

## Status

🟢 Accepted.
