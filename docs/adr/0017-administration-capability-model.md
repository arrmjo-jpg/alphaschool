# ADR-0017: The Administration Capability Model

**Status:** Accepted

**Date:** 2026-07-14

## Context

ADR-0016 establishes that Administration is realized through capabilities, not modules or pages. This ADR names those capabilities, the axes every capability is read through, and the rules governing when a new capability or a new scope tier is legitimate versus speculative.

## Decision

**1. Ten capabilities, each a durable responsibility, none a data-owning module of Administration Platform itself:**

| Capability | Owns (architecturally) | Never owns |
|---|---|---|
| Organizational Identity & Structure | Core (Organization, School, Branch) | Any record merely scoped by `branch_id` |
| Access Governance | Identity — Users, Roles, Permissions, machine/API identity, federated-identity configuration (SAML/OIDC/SCIM) | What an authenticated actor does once authorized |
| Policy & Configuration Governance | The Configuration Platform (ADR-0018) | The meaning of any key, Business Rules (ADR-0020), Reference/Master Data |
| Digital Experience Delivery | A shared Experience Kernel (brand, localization, consent, analytics) + independent surface modules (Website, Portal, Mobile) | Any surface's operational delivery; Public API documentation (belongs to Connectivity) |
| Communication & Engagement | Communications (Domain) + Notifications (Foundation) — channels, templates, routing, campaign definitions | Delivery logs, individual sent messages (Operations) |
| Connectivity & Interoperability | Provider Registry, Webhook Gateway, Sync/ETL, outbound API Platform (ADR-0019) | Federated identity (Access Governance); the data payloads exchanged |
| Asset & Facility Stewardship | A new, independent Domain module (not yet designed) — the asset registry | Sensor event streams (Operations); the platform vendor's own infrastructure (Tenancy Line) |
| Observability & Health Policy | Alert-threshold and monitoring-scope declarations | Live telemetry, dashboards, the platform's own server metrics |
| Governance, Risk & Compliance | Policy declarations (retention, security policy, safety-critical approval requirements) | The audit log itself (Core's Audit Engine, referenced not owned); enforcement mechanics |
| Platform Extensibility & Product Lifecycle | Module licensing, feature-flag definitions, deployment templates (Packages, ADR-0021) | Actual provisioning infrastructure (Tenancy Line); any specific customer's configured values |

**2. Every capability is read through four orthogonal axes, not treated as a flat list:**

- **Altitude** — where a fact is declared true: `Platform → Deployment → Organization → School (once designed) → Branch`, with **User Preferences as a separate, parallel, lower-ceremony mechanism** — never the same audited chain. `Campus`, `Building`, `Department`, `Program`, `Team`, and `Role` are explicitly **not** altitude levels: the first five are undesignated aggregates (rejected per the promotion rule below), and Role is a permission-gating concern (who may view/edit), not a value-resolution concern, and must never be conflated with it.
- **Resolution Timing** — when a value is fixed: deploy-time-declared schema, runtime-resolved value (the normal override chain), or runtime fast-path (Feature Flags — a deliberately separate, low-ceremony mechanism, never sharing storage with audited Configuration).
- **Domain Nature** — Technical (domain-agnostic, owned by Foundation/Core/Administration Platform's own mechanisms) versus Business (domain-specific, owned by the relevant Domain module via ADR-0020's pattern, never centralized).
- **Administrator Persona** — the customer's own staff versus the platform vendor's engineering team (the Tenancy Line, ADR-0016 §6).

**3. Promotion rules — the discipline that keeps this model from over-growing:**

- A new **capability** is promoted only when an existing capability demonstrably cannot absorb a concern — evaluated the same way AI was deferred in this review: distributed across Connectivity (provider), Configuration (feature toggles), and Governance (risk policy) today, promoted to its own capability only once it earns independent weight, never speculatively.
- A new **Altitude level** is promoted only for an already-real, already-designed aggregate with demonstrated cross-cutting configuration need — never invented to "future-proof" the hierarchy. Every level added is a permanent tax on every future resolution trace; the cost is real and compounding.
- A new **entry under an existing capability** (a new asset type, a new surface, a new vendor category) requires no promotion at all — this is the entire point of the model: growth happens by registration, not by re-architecture.

## Consequences

Adding Exams, Procurement, or a capability that doesn't have a name yet does not require revisiting this ADR — the question is always "which of the ten capabilities does this serve," and the answer is derivable, not designed fresh each time. This is the property that makes the model durable across a fifteen-to-twenty-year horizon: the five questions (ADR-0016) essentially never change; these ten capabilities change on the order of once a decade, and only by the promotion discipline above; concrete modules underneath them change constantly, insulated from the top two layers.

## Alternatives Considered

- **A capability per today's feature** (Website, Notifications, Third-Party, Infrastructure, Security as flat siblings). Rejected — conflates a navigation grouping with a bounded-context boundary; several of these (Security, Third-Party) dissolve entirely into existing capabilities once examined, and none of them would still make sense unmodified in fifteen years.
- **A single, generic "Everything Configurable" capability** with no internal structure. Rejected — this is ADR-0016's rejected "flat Settings table" restated at the capability level.

## References

`docs/adr/0016-administration-platform-data-boundary-and-philosophy.md`. `docs/DOMAIN_BLUEPRINT.md` §3 (Branch's "owns only its own facts" rule, directly informing Organizational Identity & Structure's boundary), Addendum A2 (Organization), Addendum B1 (Core inclusion tests, reused for capability promotion), Addendum B6 (Branch Ownership, the direct precedent for the Altitude axis). ADR-0018 through ADR-0021.
