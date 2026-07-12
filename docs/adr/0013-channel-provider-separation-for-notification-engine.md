# ADR-0013: Channel/Provider Separation for the Notification Engine

**Status:** Accepted

**Date:** 2026-07-12

## Context

The Notification Engine (Notifications, Foundation) must deliver messages across Email, SMS, WhatsApp, Push, and In-App without the Engine, or any business module, ever knowing which vendor implements a given channel for a given deployment — a direct consequence of this project's confirmed dedicated-instance-per-customer commercial model (ADR-0006), where each deployment configures its own vendor accounts. Left unspecified, vendor-specific code (a Twilio SDK call, a Firebase payload shape) would leak into business modules or the Engine itself, making a future vendor swap (UltraMsg → Meta, Twilio → another SMS gateway) a redesign rather than a configuration change — the same risk this project has already refused to accept for Storage disks and Search backends.

## Decision

**Channel vs. Provider.** A Channel (Email/SMS/WhatsApp/Push/In-App) is a contract; a Provider is a vendor's implementation of that contract. The Notification Engine and every business module depend only on the Channel contract, never a vendor, and request only "send Email/SMS/WhatsApp/Push/In-App" — never a named vendor.

**Provider structure.** One concrete Provider class per channel contract, even when a single vendor spans multiple channels (e.g., `TwilioSmsProvider implements SmsProvider` and `TwilioWhatsAppProvider implements WhatsAppProvider`, both depending on a shared internal `TwilioClient` holding credentials and HTTP transport, itself not a Provider). This keeps each Provider single-responsibility and trivially mockable per channel, while avoiding duplicated vendor credential configuration.

**Interface segregation.** Each channel's primary contract stays to its minimal common shape (`send()`); optional capabilities (delivery receipts, two-way reply capture) are separate, optionally-implemented interfaces, detected via `instanceof` only when a real business need arises — never bloating the base contract or forcing unsupported providers into no-op stubs.

**WhatsApp's bounded exception.** WhatsApp is the one channel where vendor differences are business-semantic, not merely transport-level: official-API-backed providers (Meta, and Twilio's WhatsApp product) enforce pre-approved message templates and a 24-hour customer-service session window for free-form replies; unofficial providers (e.g., UltraMsg-style) typically do not. The WhatsApp Provider contract must expose whether these rules apply, and the Template aggregate (Notifications) carries a per-provider vendor-template-reference for this channel only. This is the single, deliberate, documented crack in "the Engine never knows about vendors," scoped to WhatsApp exclusively.

**In-App is not a vendor-swappable channel.** Persisting a notification is an Engine-owned write, not a Provider concern — there is no vendor to swap for storage. Only In-App's optional real-time delivery layer maps onto a Provider-shaped concept, and this project already has that abstraction for free via its existing Broadcasting driver configuration (Reverb/Pusher/Ably) — no bespoke In-App Provider interface is to be built.

**Configuration — future-ready, single-provider in behavior today.** Every channel's configuration declares `default`, `failover`, and `routing` keys from the first implementation; only `default` is read by the resolution logic in this release. `failover`/`routing` exist as a structurally valid, functionally inert shape, so introducing real failover or purpose/country-based routing later is additive, not a schema or contract redesign.

```yaml
sms:
  default: twilio
  failover: []
  routing: []
```

**Resolution mechanism.** Implemented as a Laravel Manager (config-key-driven, `extend()`-able) — the same native mechanism already governing this codebase's `config/filesystems.php` disk resolution and `config/broadcasting.php` connection resolution. No bespoke resolver is to be invented.

**Testing.** A Null/Log Provider per channel is a first-class citizen from the first implementation, not an afterthought — no test may reach a real vendor.

**Secrets and scope.** Provider credentials are encrypted config/secrets, scoped per deployment (per the dedicated-instance commercial model), administered via Administration Platform's (ADR-0011) provider-configuration UI — never a plaintext Settings row.

**Vendor SDK isolation.** A vendor SDK type may be imported in exactly one place: inside that vendor's own Provider implementation class. No Channel contract signature, Engine internal, or business-module code may reference a vendor SDK type — this is what makes "no business code changes when swapping vendors" a structural guarantee rather than a convention someone has to remember.

**Deferred, non-normative: Provider observability.** Providers may eventually optionally expose Health checks, Metrics, Latency, and Delivery statistics, consumed exclusively by Administration Platform's monitoring/dashboard surface — never by the Engine's send path or any business module. If built, each is its own optionally-implemented capability interface (`ReportsHealth`, `ExposesMetrics`), following the same interface-segregation principle above. Not part of the first implementation; recorded here only so the frozen contract is not later redesigned to accommodate it.

## Consequences

Swapping a vendor for any channel (except the documented WhatsApp exception) requires a configuration change and a new Provider class, never a change to the Engine or any business module. The WhatsApp exception is bounded and explicit, not a silent gap discovered later. One decision remains explicitly open: whether a permanent delivery failure (e.g., Push's invalid-token case) flows back to the originating business workflow — linked to the same open question already carried from ADR-0012, not resolved here.

## Alternatives Considered

- **One Provider class implementing multiple channel interfaces for a multi-channel vendor (e.g., `TwilioProvider implements SmsProvider, WhatsAppProvider`).** Rejected in favor of one class per channel sharing an internal client — keeps each Provider single-responsibility and independently testable, while a shared internal client still eliminates credential duplication.
- **A single active provider per channel with no failover/routing shape.** Rejected — real commercial messaging needs (purpose-based routing, automatic failover, country-based routing) are common enough that the configuration shape should exist from day one, even though the resolution behavior is deferred; retrofitting the shape later would require a redesign this ADR exists to avoid.
- **Treat In-App symmetrically with the other four channels, requiring its own Provider interface.** Rejected — In-App has no external vendor for persistence; forcing a Provider abstraction onto it would duplicate the Broadcasting driver abstraction this project already has.
- **Build Provider health/metrics/latency capabilities now, since Administration's dashboards will eventually want them.** Rejected — no real consumer exists yet; recorded as a non-normative future note instead, consistent with "promotion, not prediction."

## References

`docs/adr/0006-dedicated-instance-commercial-model.md`. `docs/adr/0011-administration-platform-bounded-context.md`. `docs/adr/0012-communications-as-thin-aggregator-domain-module.md`. `docs/developer/administration-platform-and-communications.md`. `config/filesystems.php`, `config/broadcasting.php` (the existing Manager-pattern precedent this ADR reuses). Raised during a dedicated Notification Engine architecture session, 2026-07-12, before any Sprint 2.5+ implementation.
