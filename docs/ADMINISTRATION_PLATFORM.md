# AlphaSchool ERP — Administration Platform Blueprint

**Status:** Architecture frozen 2026-07-14. This is the companion architectural reference for the Administration Platform — the backend counterpart to `docs/ADMIN_PLATFORM.md` (frontend Workspace UX) the way `docs/DOMAIN_BLUEPRINT.md` is the reference for business-domain architecture. It carries the same governance weight: no redesign without an approved ADR (`docs/adr/0016` through `0022`). It does not contain code, migrations, or implementation detail — those are `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md`'s job.

**Relationship to prior documents:** `docs/adr/0011-administration-platform-bounded-context.md` named Administration Platform and fixed its high-level scope. This document is that ADR's deferred "implementation-planning pass," now complete. It does not contradict ADR-0011 — it specifies the internal shape ADR-0011 left open.

**The word "Settings" does not appear anywhere below as an architectural concept.** It is retired from this project's vocabulary (ADR-0016 §1) and survives only as informal UI language, if at all.

---

## 1. The five eternal questions

Every administrative act in any large organization reduces to five questions, independent of industry:

1. Who are we, structurally?
2. Who can act, and as what?
3. What is allowed, and by what declared rule?
4. How do we reach the world, and what do we own to do it?
5. Can we prove what happened, and are we compliant?

These questions do not change when AlphaSchool adds Procurement, Exams, or a capability with no name yet. They are the top of a three-layer model: **five questions → ten capabilities → concrete modules.** The top layer is near-permanent. The middle layer changes on the order of once a decade, only by the promotion discipline in §3. The bottom layer — Website, Infrastructure, whichever vendor Provider — churns constantly and is insulated from the top two by the capability abstraction.

## 2. The ten capabilities

| Capability | Owns | Never owns | Owning module |
|---|---|---|---|
| Organizational Identity & Structure | Organization, School, Branch profile facts | Any record merely scoped by `branch_id` | Core / Identity |
| Access Governance | Users, Roles, Permissions, machine/API identity, federated-identity config | What an authorized actor does | Identity |
| Policy & Configuration Governance | The Configuration Platform (§4) | Key meaning, Business Rules, Reference/Master Data | Administration Platform |
| Digital Experience Delivery | Shared Experience Kernel (brand/localization/consent/analytics) + surface modules | Operational delivery; developer-facing API docs | New Website/Mobile/Portal modules + Foundation kernel |
| Communication & Engagement | Channels, templates, routing, campaign definitions | Delivery logs, individual sent messages | Communications (Domain) + Notifications (Foundation) |
| Connectivity & Interoperability | Provider Registry, Webhook Gateway, Sync/ETL, outbound API Platform | Federated identity; exchanged data payloads | Administration Platform (mechanism) + consuming modules |
| Asset & Facility Stewardship | The asset registry (not yet designed) | Sensor event streams; platform vendor's own infrastructure | New Domain module |
| Observability & Health Policy | Alert-threshold declarations | Live telemetry, dashboards, vendor server metrics | Core/Ops |
| Governance, Risk & Compliance | Policy declarations (retention, security, safety-critical approval) | The audit log itself; enforcement mechanics | Administration Platform (declares) + every module (enforces) |
| Platform Extensibility & Product Lifecycle | Module licensing, feature flags, deployment templates/Packages | Provisioning infrastructure; any one customer's actual values | Core (licensing) + small Platform Governance concern |

None of the ten is a "page." Several compose across multiple backend modules on the frontend as a single Workspace (`docs/ADMIN_PLATFORM.md`'s existing principle) without any of this ownership changing.

## 3. Four axes every capability is read through

- **Altitude** — `Platform → Deployment → Organization → School (once designed) → Branch`, plus **User Preferences as a separate, parallel mechanism**. `Campus`/`Building`/`Department`/`Program`/`Team` are rejected as altitude levels (undesignated aggregates); `Role` is rejected as an altitude level (a permission concern, never a value-resolution concern).
- **Resolution Timing** — deploy-time-declared schema / runtime-resolved value / runtime fast-path (Feature Flags, deliberately separate storage).
- **Domain Nature** — Technical (Foundation/Core/Administration-owned) versus Business (Domain-owned via the Effective-Dated Business Policy pattern, ADR-0020).
- **Administrator Persona / the Tenancy Line** — the customer's own staff versus AlphaSchool's own engineering team. Anything belonging to the latter is entirely out of scope of this document.

**Promotion rules:** a new capability is promoted only when no existing one can absorb a concern. A new Altitude level is promoted only for an already-real, already-designed aggregate with demonstrated cross-cutting need. Neither is ever added speculatively — see Blueprint Addendum B1's "promotion, not prediction" test, applied here identically.

## 4. The Configuration Platform

The sole realization of Policy & Configuration Governance. Not an "engine" — it owns a registry, a resolver, a fifteen-field metadata model, dependency declarations, and integration points into Core's Approval and Audit engines.

**Registration contract:** `DeclaresSettingsSchema` — deploy-time manifest, code-reviewed, never runtime-mutable. Fields: key, type, translatable-category (Blueprint B5's three-way test), default, `required`, eligible-altitudes, versioned (mandatory for calculation-feeding values, §7), owning module, capability, Data Classification, approval-required, required-permission-to-view, required-permission-to-edit, restart-required, cache-TTL, `requires` (simple equality dependency declarations only), validation rules, migration strategy, deprecation status.

**Explicitly rejected metadata:** an optional audit-required toggle (always on, unconditionally), an `encrypted` flag (signals the field was miscategorized — it belongs in the Credential Vault, not here), an `environment` flag (only values vary by environment, never key existence).

**Resolution:** `resolve(key, scopeContext) → {value, resolvedAtAltitude, trace}` — pull-based, never pushed, no caching until profiling proves it necessary (Addendum A6's discipline). The trace is a first-class artifact, not an internal detail (ADR-0021 §4).

**Configuration Objects are out of scope of this mechanism.** The moment a value needs multiple instances or sub-fields, it is Reference/Master Data, owned by the relevant module using existing value objects (Blueprint §5), never a JSON blob in a Configuration value column.

## 5. The Integration Platform

Four mechanisms under Connectivity & Interoperability, generalizing ADR-0013's Channel/Provider pattern:

- **Provider Registry** — capability contract + credential schema + health check, per vendor category.
- **Webhook Gateway** — inbound verification, replay protection, translation into a Domain Event (§14 of the Domain Blueprint).
- **Sync/ETL Orchestration** — bidirectional data exchange; configuration is Administration-layer, run history is Operations-layer.
- **Outbound API Platform** — developer/partner-facing key issuance and rate limits.

**Glossary, precisely:** Provider = a concrete implementation of a capability contract. Connector = a packaged bundle of Provider/Webhook/Federation registrations for one vendor. Federation (SAML/OIDC/SCIM/LDAP) = Access Governance, not Connectivity. Gateway = an architectural role, not its own capability. API Client = internal implementation detail of a Provider. API Consumer = an external party calling AlphaSchool's own API. Client-side tracking pixels = plain Configuration, never a Provider.

## 6. Business Rules — the Effective-Dated Business Policy pattern

Business Rules (Promotion, Admission, Attendance, Grading rules; Fee and Discount Policies; Registration Windows) are never Configuration and never owned by Administration Platform. They generalize Blueprint Addendum A5's Billing Policy pattern: small, independently effective-dated entities, owned by the Domain module that governs the process, optionally approval-gated through Core's existing Approval Engine. Full detail: `docs/adr/0020-effective-dated-business-policy-pattern.md`.

## 7. The Administration Experience Layer

A derived, read-mostly layer over the above — never a shadow copy of live state. Search (reuses Addendum D5), Dependency Graph (compiled from `requires` declarations, powers both the Explorer and Impact Analysis), Resolution Trace (§4, exposed), Configuration Timeline (a filtered view of the existing Audit Engine), Health Engine + weighted Score (never presentable without drill-through), Readiness Checks (`RegistersReadinessChecks`, extensible per module), Packages and Snapshots (one concept, provenance-tagged), Import/Export (reuses Sprint 3.2's dry-run convention), Rollback (a proposed new write from history, never a raw rewind — governance can never be bypassed), Environment Promotion (Organization/Branch-altitude Business Configuration only — never Deployment-altitude secrets). Full detail: `docs/adr/0021-administration-experience-layer.md`.

## 8. Named patterns added to the shared catalog

Alongside Temporal, Assignment, Approval, Workflow, Media, Audit, Number Generator, Notification, Versioning, and Duplicate-Detection (Blueprint §6/§13):

- **Registry Pattern** — deploy-time, code-populated, low-cardinality, answers "what can exist." Never used for user-created business entities (the Asset/Template "Registry" naming trap this review explicitly rejected).
- **Content Lifecycle Pattern** — Draft → Published → Archived/Deprecated, shared by Website Pages, Communication Templates, and Configuration Packages.
- **Effective-Dated Business Policy Pattern** — §6 above.

## 9. What must never be violated

1. Administration Platform's own schema is bounded to exactly four shapes (Configuration Registry, Provider Registry/Vault, Package/Snapshot artifacts, the Experience Layer's derived compilations) — enforced by architecture test, not convention.
2. History is never overwritten — Rollback, Import, and every write path append or version, per Blueprint §7.
3. Business Rules never live in generic Configuration, regardless of schedule pressure.
4. The Tenancy Line — Deployment-altitude Technical Configuration is never admin-UI-editable.
5. Approval-gating on `approval-required`/safety-critical keys can never be bypassed by Rollback, Import, or Promotion.
6. The Registry mechanism is never used to store rich, user-created business data (Assets, Content).
7. Federated identity is Access Governance's concern, never Connectivity's.

## References

`docs/adr/0011`, `docs/adr/0016` through `0022`. `docs/DOMAIN_BLUEPRINT.md` §5, §6, §7, §13, §14, Addendum A5, A6, A7, B1, B5, B6, D5. `docs/ADMIN_PLATFORM.md`. `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` (execution).
