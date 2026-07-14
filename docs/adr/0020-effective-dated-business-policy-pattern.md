# ADR-0020: Effective-Dated Business Policy Pattern

**Status:** Accepted

**Date:** 2026-07-14

## Context

This review's first serious challenge asked whether Academic/Admissions/Finance rules (Promotion Rules, Admission Rules, Attendance Rules, Grading Policies, Fee and Discount Policies, Registration Windows) belong inside Policy & Configuration Governance. They do not — but the correct resolution isn't a new capability that owns this data centrally. Blueprint Addendum A5 already solved this exact problem for Finance specifically: "several small, independently effective-dated policy entities (Sibling Discount, Employee Discount, Scholarship, Late Fee, Installment), all owned by Finance." This ADR generalizes A5 into a named, reusable pattern any Domain module applies to its own rules, and states explicitly what must never be treated as Configuration.

## Decision

**1. Business Rules are never Configuration, and never owned by Administration Platform.** A Promotion Rule, an Admission Rule, a Grading Policy — these determine business *outcomes* (does a student pass, graduate, get admitted), often through conditional logic beyond a typed scalar, and several of them feed calculations that must be historically reproducible per Blueprint §7. They are, and remain, owned entirely by the Domain module that already governs the process they decide: Promotion Rules by Academic, Admission Rules by Admissions, Fee and Discount Policies by Finance (unchanged from A5).

**2. The Effective-Dated Business Policy pattern**, added to the shared-pattern catalog (Blueprint §6/§13) as the generalization of A5: a Business Rule is modeled as a small, independently effective-dated entity (`effective_from`/`effective_until`, `reason_code`, `assigned_by`/`ended_by` — the same column convention already used identically across every historized entity, Blueprint §6), owned by its Domain module's own schema, optionally routed through the existing Approval Engine (Core, Sprint 1.2) when the rule is safety-, legal-, or financially-critical — the same governance hook ADR-0018 wires for `approval-required` Configuration, applied here to Business Rules instead. Never a new approval mechanism; a new consumer of the one that already exists.

**3. The Domain Nature axis, formalized.** Every Administration concern is Technical (domain-agnostic, would make sense in an unrelated ERP, passes Blueprint B1's test — owned by Foundation/Core/Administration Platform's own mechanisms) or Business (domain-specific, owned by the relevant Domain module via this pattern). This is not a capability of its own; it is an axis explaining, in advance, why Organizational Identity & Structure's data and Finance's Billing Policy both use effective-dating yet are never owned by the same module.

**4. Shared Reference Data that multiple modules consume but none of them own** (the Academic Calendar, public and school holidays, working-day definitions) is a distinct third case from both Configuration and Business Rules: it is Reference/Master Data owned by Organizational Identity & Structure (ADR-0017) — the same relationship Branch already has to every module that references it without owning it.

## Consequences

The naming collision that prompted this review's original complaint — "Grading Policy" sounding like it should be a setting — is resolved without inventing a new owning capability. Academic, Admissions, and Finance each carry more Business Rule entities over time using one consistent, already-proven pattern, rather than each reinventing effective-dating or drifting toward storing a rule as a Configuration value because it was faster in the moment. That drift is named explicitly as the top delivery risk in ADR-0022.

**Amendment (2026-07-14):** the enforcement gap named above — "drifting toward storing a rule as a Configuration value because it was faster" — had no technical trap anywhere in the architecture, the one boundary in this review protected by convention alone. It is now closed by ADR-0018 Decision 10's registration-time integrity heuristic, added in the same amendment pass: a key's dependency fan-out, validation-rule complexity, and Data-Classification/`approval-required` combination are flagged for explicit human review at registration time, rather than this rule relying on documentation and code-review discipline alone.

## Alternatives Considered

- **A "Business Governance" capability owning all Business Rules centrally.** Rejected — this would require Administration Platform to hold deep, module-specific domain logic (what makes a student eligible for promotion), directly violating Blueprint B1's domain-agnosticism test and ADR-0016's data boundary in the same way a standalone Erasure Engine in Core was already rejected for Identity Maintenance (Addendum C).
- **Treating Business Rules as ordinary Configuration with a richer value type.** Rejected — loses per-rule audit granularity, effective-dating, and approval-gating that a single Configuration value's metadata cannot represent without becoming, in effect, a second, worse implementation of the Temporal pattern.

## References

`docs/DOMAIN_BLUEPRINT.md` Addendum A5 (Billing Policy pattern, the direct precedent this ADR generalizes), §6 (Temporal/Effective-Dating pattern, Assignment pattern), §7 (Historical Data Rules), Addendum B1 (Core inclusion tests). `docs/adr/0016-administration-platform-data-boundary-and-philosophy.md`, `docs/adr/0018-configuration-platform-resolution-and-metadata.md` (the shared Approval Engine hook this pattern reuses).
