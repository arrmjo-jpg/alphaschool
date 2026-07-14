# ADR-0019: Integration Platform — Provider Registry, Webhook Gateway, Sync/ETL, API Platform

**Status:** Accepted

**Date:** 2026-07-14

## Context

ADR-0013 (2026-07-12) established Channel/Provider separation for the Notification Engine specifically. This review found the same shape needed generalizing across every outbound vendor relationship (Payments, AI, Storage, Maps), while also surfacing three mechanisms ADR-0013 never had to address: inbound webhooks, identity federation, and bidirectional data synchronization. This ADR generalizes ADR-0013's pattern into the full Connectivity & Interoperability capability (ADR-0017) and gives precise names to terms used loosely throughout this review (Connector, Integration, Gateway, Event Bridge, and others).

## Decision

**1. Four mechanisms, one capability, none of them "Third Party Integrations" as a data-owning concept.**

- **Provider Registry** (generalizes ADR-0013) — a **capability contract** per vendor category (Payment, AI, Storage, Maps, SMS, …), a **credential schema**, and a **health-check callback**, resolved via the same Manager-pattern mechanism ADR-0013 already specified. One contract per category; adding a new vendor within an existing category (DeepSeek joining OpenAI/Gemini/Claude) requires zero changes to the Registry itself — only a new Provider implementation.
- **Webhook Gateway** — inbound-only, the mirror of Provider Registry's outbound calls: signature verification, replay protection, and translation of a verified inbound event into a standard internal Domain Event (Blueprint §14) for the owning module to consume. This translation step *is* the mechanism sometimes informally called an "Event Bridge" — not a fifth mechanism, a precise description of the Gateway's own function.
- **Sync/ETL Orchestration** — for bidirectional, ongoing data exchange (e.g., a future Google Classroom roster sync). Its *configuration* (field mappings, cadence — the informal terms "Transformation" and "Mapping" both refer to a required sub-object of this registration, not separate capabilities) is Administration-layer; its *run history and checkpoints* are Operations-layer, high-cardinality, living in the owning module's own operational schema — the identical Administration/Operations split already applied to Notifications' delivery logs (ADR-0016 §3).
- **Outbound API Platform** — the developer/partner-facing side: API key issuance, rate-limit tiers, and versioning/deprecation policy for AlphaSchool's *own* exposed APIs. An external party calling into AlphaSchool is an **API Consumer**; code AlphaSchool writes to call a vendor is an **API Client**, an internal implementation detail of a Provider, never a separate architectural concept.

**2. Federated identity is relocated to Access Governance, not owned here.** SAML, OIDC, LDAP, and SCIM change *who the platform trusts as an actor* — that is Access Governance's (ADR-0017) responsibility, not Connectivity's. This corrects an earlier position in this review that filed Federation under Integrations; it does not belong there. A **Connector** — a packaged bundle of Provider, Webhook, and (where relevant) Federation registrations for a single vendor (e.g., "the Google Connector" bundling Maps' Provider registration, Classroom's Sync/ETL registration, and Google's Federation registration) — is a convenience grouping across mechanisms and capabilities, never a fifth primitive of its own.

**3. Client-side tracking is explicitly not a Provider.** Analytics/marketing pixels (Google Analytics, Facebook Pixel, TikTok Pixel, and similar) carry no server-side credential and no health check — they are plain Configuration values (ADR-0018) consumed by Digital Experience Delivery surfaces, never registered into the Provider Registry. Registering them here would apply a mechanism designed for stateful vendor relationships to what is, structurally, a string.

**4. Vendor SDK isolation, reaffirmed.** A vendor SDK type is importable in exactly one place: that vendor's own Provider (or Connector) implementation — no Channel/capability contract, Gateway internal, or business-module code may reference a vendor SDK type, exactly as ADR-0013 already established for Notifications, now binding across every Connectivity instance.

## Consequences

Every future vendor relationship — a payment gateway, an AI model provider, a future hardware-integration protocol under Asset & Facility Stewardship — is a new Provider implementing an existing or newly-declared capability contract, never a bespoke integration built outside this mechanism. "Integration Platform" is retired as an implied fifth mechanism and confirmed as the informal umbrella term for these four, never itself a data owner.

## Alternatives Considered

- **One flat "API Keys" page holding every vendor's credentials undifferentiated.** Rejected — this was the original complaint that opened this entire review; it also cannot represent the genuinely different shapes of Provider, Webhook, and Sync/ETL relationships.
- **Federation kept under Connectivity, alongside Providers.** Rejected on reflection during this review — conflates "how we authenticate an actor" with "how we exchange data with an external system," two different capabilities with different risk profiles and different owning modules.
- **A generic, capability-agnostic "Integration" entity with arbitrary configuration.** Rejected — this is ADR-0016's rejected flat-table anti-pattern applied to vendor relationships specifically; each of the four mechanisms above has a genuinely different shape and needs its own contract.

## References

`docs/adr/0013-channel-provider-separation-for-notification-engine.md` (the direct precedent this ADR generalizes). `docs/adr/0016-administration-platform-data-boundary-and-philosophy.md`, `docs/adr/0017-administration-capability-model.md`. `docs/DOMAIN_BLUEPRINT.md` §14 (Domain Events, the target of the Webhook Gateway's translation step).
