# ADR-0011: Administration Platform as a Foundation-Tier Bounded Context

**Status:** Accepted

**Date:** 2026-07-12

## Context

A dedicated domain-analysis session identified a real, recurring gap: Settings resolution, generic Favorites/Tags/Notes (already named in Addendum D2/D3 with no owning module), Custom Field governance, Audit console + retention policy (a known-open risk since Phase 1), Import/Export, Module Licensing evaluation, and Data Classification enforcement are all cross-cutting capabilities every Domain module needs, none of which naturally belongs to any single Domain module. Left unassigned, each Domain module would build its own version of each — the infrastructure-layer equivalent of the God-Object failure this project has already refused to repeat at the aggregate level.

Separately, `docs/ADMIN_PLATFORM.md` already claims the term "Admin Platform" for the frontend Workspace UX architecture (navigation, dashboards, widgets — a presentation-layer concept with no backend lifecycle). This ADR's bounded context shares that name by coincidence, not by relation, and the collision must be resolved explicitly rather than left to cause ambiguity later.

## Decision

Introduce **Administration Platform** as a Foundation-tier bounded context (module namespace `App\Modules\Administration\*`), alongside Notifications/Settings/Media, responsible for: Settings/Configuration resolution (absorbing the Blueprint §1's existing Settings charter), Custom Field governance, generic Favorites/Tags/Notes, an Audit console targeting Data Classifications (see below) rather than individual tables, a format-agnostic Import/Export framework, Module Licensing *evaluation* (consuming, never owning, Core's `Organization`/`OrganizationModule` — and any future `Subscription` aggregate, should Licensing ever be promoted beyond a flag, per the Licensing analysis below), and Data Classification declaration/enforcement.

**Explicitly excluded:** Identity, People, Authorization, Organization, Branch (frozen Phase 2 — Administration only consumes their Permission Groups to gate its own screens); every Domain module's business processes (HR, Finance, Admissions, Academic, Inventory, Library, Transportation, LMS, Communications); Reporting's analytics logic (Administration owns only the dashboard-widget registry, never a report's query logic); the `License`/`Subscription` aggregate itself, which belongs in Core beside Organization once promoted.

**Naming disambiguation:** `docs/ADMIN_PLATFORM.md`'s "Admin Platform" continues to refer exclusively to the frontend Workspace UX architecture. This ADR's bounded context is referred to in architecture documents as "Administration Platform" (backend) to distinguish it in prose; its code namespace (`App\Modules\Administration`) never collides with the frontend's terminology in practice.

**Licensing:** `OrganizationModule` (Core, frozen Sprint 2.3) remains the correct implementation today — a flag + expiry is sufficient while licensing has no real subscription/renewal/add-on workflow. Promote it to a full `Subscription`/`SubscriptionLineItem` aggregate (Core-adjacent to Organization, following the Temporal Pattern for term periods) when either: a real commercial need for a renewal workflow or module add-on arrives, **or** licensing accumulates multiple business-process verbs (trial, renewal, upgrade, downgrade, suspension) against the same data — the second signal being the more reliable one, since it marks the shift from "a flag with an expiry" to "a stateful business aggregate." Administration's Licensing Evaluation Engine and administrative console consume whichever shape exists at the time without needing to change.

**Data Classification:** every model declares exactly one classification from a fixed, developer-maintained, closed-but-extensible enumeration (extended only when a genuinely new class of data appears — the same treatment already given to `OrganizationModule::MODULE_CODES`). Starting vocabulary: Identity, Financial, Academic, Operational, Audit — deliberately coarse; resist further splitting until a real retention rule needs the distinction. Retention policy targets a classification by default, with a documented per-model override remaining possible for a genuine exception. Declaration must be enforced by an architecture test (mirroring Sprint 2.4's Identity Maintenance contract-declaration test), not left as an undeclared convention.

**Import/Export:** the engine operates on a normalized, generator-based record stream. Source adapters (CSV first; Excel/JSON/XML/API-based later) are additive implementations of one contract; the mapping/validation/error-collection engine and target writer are format-agnostic. The source-adapter contract must be stream/generator-shaped from the CSV implementation onward, specifically so API-based imports (paginated, asynchronous) fit the same contract later without a redesign.

## Consequences

Every Domain module gets Settings, Custom Fields, Favorites/Tags, Import/Export, and Audit/Retention for free, built once at Foundation tier, instead of once per module. The naming collision with `docs/ADMIN_PLATFORM.md` is resolved by convention (backend "Administration Platform" vs. frontend "Admin Platform"), not by renaming the already-frozen frontend document. Two decisions remain explicitly open and are not resolved by this ADR: (1) whether admin-managed lookup/reference tables (Nationality, Religion, Language) live in Core or in Administration; (2) whether "Administration" becomes one literal module namespace or stays a conceptual grouping realized across several sibling Foundation modules that already exist independently (Settings, Notifications) — this ADR resolves the *scope* of Administration Platform, not its final internal code organization, which is deferred to its own implementation-planning pass.

## Alternatives Considered

- **Split these capabilities across several independent Foundation modules with no unifying concept.** Rejected for reasoning purposes (each may still be its own deptrac-bounded module internally), but Administration Platform is retained as the umbrella architectural concept so future design conversations reason about these capabilities coherently rather than rediscovering the same cross-cutting need once per module.
- **Fold Licensing evaluation into Organization/Core directly.** Rejected — Organization owns the underlying licensing *fact*, but the evaluation engine and administrative console are cross-cutting concerns serving every Domain module's gating middleware, matching the established Number-Generator/Approval-Engine precedent (Core owns the mechanism, Administration administers it).
- **Rename the frontend `docs/ADMIN_PLATFORM.md` to resolve the naming collision.** Rejected — that document is already frozen and adopted; disambiguating by prose convention is cheaper and non-disruptive.

## References

`docs/DOMAIN_BLUEPRINT.md` §1, §2, Addendum D2/D3 (Tags), Addendum E (this session's summary). `docs/ADMIN_PLATFORM.md`. `docs/adr/0009-identity-maintenance-contract-boundary.md` (the negative-test-proven declaration discipline this ADR's Data Classification enforcement reuses). Raised during a dedicated Administration Platform architecture session, 2026-07-12, before any Sprint 2.5+ implementation.
