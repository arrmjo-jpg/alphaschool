# ADR-0007: Identity Maintenance as a Foundation Module

**Status:** Accepted

**Date:** 2026-07-01

## Context

The architecture's "never overwrite history" principle is in direct tension with legal rights to erasure/correction of personal data (GDPR and equivalents, often stricter for minors' data). An initial proposal treated this as a standalone "Erasure Engine" living in Core, but Core is required to be domain-agnostic (ADR-independent rule from the Core-boundary tests), and safely merging or anonymizing a Person requires deep, domain-specific knowledge of what's attached to them (Enrollments, Employments, Invoices, Media) — which Core cannot have.

## Decision

Introduce `Identity Maintenance` as its own Foundation-tier module (alongside People, not inside it, and not in Core), responsible for Person Merge, Duplicate Resolution, Identity Correction (policy/approval layer), Identity Recovery, and Person Anonymization. It orchestrates across every Domain module via two standard contracts every Person-referencing module implements — `ReassignsIdentityReferences` and `RedactsPersonalData` — rather than direct cross-module table access. Merge and Anonymization always require Approval-Engine gating with no self-approval exception, even for Super Admin. Merge is built reversible-by-construction (a `merge_reassignment_log` records every reassigned reference); Anonymization is deliberately irreversible once executed, by legal design.

## Consequences

The five capabilities share one coherent governance/audit/approval umbrella instead of being scattered across modules with inconsistent rules. Every future Domain module must declare (implement, or explicitly declare "none") both contracts as part of its initial build — enforced by an architecture test scanning for undeclared Person-referencing columns. This retracts and replaces the earlier "Core Erasure Engine" framing.

## Alternatives Considered

- **A standalone Erasure Engine in Core.** Rejected — inconsistent with Core's own domain-agnosticism rule, since anonymization requires knowledge of domain-specific attachments Core is not supposed to have.
- **Folding these capabilities into People module directly.** Rejected — the risk profile (rare, high-stakes, hard-to-reverse, needs elevated permission and cross-module write access) is categorically different from People's day-to-day identity-content management, and deserves a sharp, explicit boundary the way Enrollment was separated from Student.

## References

`docs/DOMAIN_BLUEPRINT.md` Addendum C (all subsections).
