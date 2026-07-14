# ADR-0016: Administration Platform — Data Boundary & Core Philosophy

**Status:** Accepted

**Date:** 2026-07-14

## Context

`docs/adr/0011-administration-platform-bounded-context.md` (2026-07-12) named Administration Platform as a Foundation-tier bounded context absorbing the Blueprint §1 Settings charter, governed by one stated principle: "administers, never re-implements." That ADR fixed *scope* (Settings resolution, Custom Fields, Favorites/Tags, Audit/Retention, Import/Export, Licensing) but deliberately deferred the *internal shape* of that scope to "its own implementation-planning pass."

A dedicated, multi-session architecture review (2026-07-13 through 2026-07-14) ran that pass. It started from a request to organize a legacy system's ~100 independent Settings pages and arrived somewhere structurally different: not a bigger Settings module, but a durable **Administration Domain Model** — a small set of eternal responsibilities, realized through named capabilities, with a hard rule about what Administration Platform itself is permitted to own. This ADR, and its five siblings (ADR-0017 through ADR-0021), formalize that review's conclusions. ADR-0022 formalizes the delivery principles for building it.

## Decision

**1. The word "Settings" is retired from this project's architecture vocabulary.** It may survive as UI copy, never as a concept in an ADR, a capability name, or a table name. Everywhere "Settings" was used loosely in prior documents, read it as **Configuration** (round-tripped through ADR-0018's narrower, precise meaning) or as one of the other data categories defined below.

**2. Administration is answered by five eternal questions, never a feature list.** Who are we, structurally? Who can act, and as what? What is allowed, by what declared rule? How do we reach the world, and what do we own to do it? Can we prove what happened, and are we compliant? These questions do not change when the industry changes — a hospital ERP asks the identical five. They are realized through **ten Administration Capabilities** (ADR-0017), each a durable responsibility, never a page or a module name.

**3. Administration and Operations are two layers inside every module, not two modules.** Formal test: **Administration owns low-cardinality, low-churn entities that other records reference. Operations owns high-cardinality, high-churn records that reference them.** A table whose row count is expected to scale with business activity (students, messages, tickets, sensor events) rather than with the count of structural/reference entities must never live inside an Administration capability's own schema, regardless of how "configurable" an individual field feels. This is the Master Data / Transactional Data distinction, adopted as the organizing principle rather than an afterthought.

**4. Administration Platform's own schema is permanently bounded to four shapes, and nothing else, ever:** the Configuration Registry (schema declarations + resolved values, ADR-0018), the Provider Registry and Credential Vault (ADR-0019), Package/Snapshot artifacts (ADR-0021), and the Experience Layer's derived, rebuildable compilations (the Dependency Graph, ADR-0021). It **never** owns Content, Reference/Master Data, Operational data, or Business Rules — those are always owned by the Domain or Foundation module the concern actually belongs to, consumed by Administration Platform's generic mechanisms, never re-implemented inside them.

**5. This boundary is enforced structurally, not by convention.** An architecture test — the same mechanism already proven for Identity Maintenance's contract declarations (Sprint 3.1) and for `deptrac`'s module-dependency graph — must fail CI the moment Administration Platform's own codebase gains a migration outside the four permitted shapes, or a dependency on another module's Eloquent models or business logic outside the two declared registration contracts (ADR-0018's `DeclaresSettingsSchema`, ADR-0019's `DeclaresProviderSlots`). This test is a Phase 0 deliverable (ADR-0022) — written and proven against a deliberate violation before the first real migration exists, the same sequencing already used for Core's temporal-pattern enforcement (Blueprint Addendum A3).

**6. The Tenancy Line.** Any concept whose real administrator would be AlphaSchool's own engineering/SRE team, not a customer's own staff, is entirely out of scope of the Administration Domain Model — it belongs to the platform vendor's internal DevOps/provisioning tooling, a separate system with a separate user base. This line is what keeps Asset & Facility Stewardship (ADR-0017) from absorbing the platform's own cloud infrastructure, and what keeps Platform Extensibility (ADR-0017) from absorbing the actual provisioning pipeline.

## Consequences

Every future capability, module, or integration is evaluated against this ADR's boundary before a line of schema is written: does it belong to one of Administration Platform's four permitted shapes, or does it belong to a real owning module, merely administered here? The single most likely governance failure this ADR exists to prevent — a business rule quietly ending up in Administration Platform's generic write path "because it's faster" — is named explicitly in ADR-0020 and ranked as the top implementation risk in ADR-0022.

## Alternatives Considered

- **One flat, undifferentiated Settings table**, the legacy shape this review was commissioned to replace. Rejected — this is the mechanism, not merely the symptom, of the original ~100-page sprawl: it made every concern's home ambiguous by construction.
- **A capability-per-module silo**, where each Domain module builds and administers its own configuration UI independently. Rejected — reproduces the "every feature invents its own settings page" disease at a coarser grain, and forfeits the one genuine cross-cutting value Administration Platform provides: one declared, auditable, override-resolvable mechanism, reused by everyone.
- **Deciding the White-Label / multi-tenancy business model first, and designing Administration around it.** Rejected, deliberately, per explicit project instruction — White Label is a *consumer* of this architecture (via the Altitude axis, ADR-0017), not its foundation. Optimizing the Administration Domain Model around one deployment model before it existed would have been prediction, not architecture.

## References

`docs/adr/0011-administration-platform-bounded-context.md` (this ADR extends its scope with the full internal model, does not supersede it). `docs/adr/0001-person-as-identity-substrate.md` (B1's Core-inclusion tests, reused here for capability-promotion discipline). `docs/DOMAIN_BLUEPRINT.md` §6, §7, §13, Addendum A3, A6, B1. ADR-0017 through ADR-0022 (siblings).
