# Administration Platform, Communications, and the Notification Engine

**Status:** Architecture frozen 2026-07-12 (ADR-0011, ADR-0012, ADR-0013). This is a design-only session's output — nothing described here has been implemented yet, and none of it currently occupies a scheduled sprint in `docs/IMPLEMENTATION_PLAYBOOK.md` (see that document's own new note on this). Recorded now so the architecture doesn't have to be rediscovered when its implementation is eventually planned, the same discipline already applied to Family's pre-Sprint-2.5 design session.

## Why four owners, not two

"Communication" in this system is not one concern — it splits cleanly across four owners, and conflating any two of them was the recurring mistake this session's review process caught and corrected:

| Owner | Layer | Charter |
|---|---|---|
| **Identity** | Foundation (existing) | OTP *mechanics* only — generate, store, verify, expire. Already built (`StepUpAuthenticationService`, Sprint 2.2). Never renders content, never picks a channel or vendor. |
| **Notifications** | Foundation (existing, Blueprint §1) | The Notification Engine: Channel/Provider abstraction, bilingual template rendering, preference resolution (ADR-0008), delivery tracking, retry. Knows nothing about Students, Guardians, Invoices, or admission decisions. |
| **Communications** | Domain (new, ADR-0012) | A deliberately thin aggregator for audience-broad, cross-module messaging (Broadcast/ScheduledMessage/Campaign) — mirrors Finance's already-granted aggregator exception. Never renders or delivers; always calls Notifications. |
| **Administration Platform** | Foundation (new, ADR-0011) | The administrative console over Notifications (template authoring, provider config, delivery-history views) plus every other cross-cutting governance concern (Settings, Custom Fields, Favorites/Tags, Audit/Retention, Import/Export, Licensing evaluation, Data Classification). Administers; never re-implements. |

## Administration Platform (ADR-0011)

A Foundation-tier bounded context, deliberately separate in name from `docs/ADMIN_PLATFORM.md`'s frontend Workspace UX architecture (a naming coincidence resolved by convention, not by renaming the already-frozen frontend document).

**Owns:** Settings/Configuration resolution; Custom Field governance (`custom_field_definitions` + `custom_attributes`, with an explicit JSON-to-real-column promotion path); generic Favorites/Tags/Notes (one polymorphic implementation, filtered per context in the UI); an Audit console targeting Data Classifications rather than individual tables; a format-agnostic Import/Export framework; Module Licensing *evaluation* (never the underlying license data — see below); Data Classification declaration and enforcement.

**Never owns:** Identity/People/Authorization/Organization/Branch (frozen Phase 2 — consumed for Administration's own screen-gating only); any Domain module's business processes; Reporting's analytics logic (only the dashboard-widget registry).

**Licensing.** `OrganizationModule` (Core, Sprint 2.3 — a flag + expiry) stays correct today. Promote to a full `Subscription`/`SubscriptionLineItem` aggregate, living in Core beside Organization (not in Administration), when either a real subscription/renewal/add-on need arrives, **or** licensing accumulates multiple business-process verbs (trial, renewal, upgrade, downgrade, suspension) against the same data — the second signal is the more reliable trigger, since it marks the shift from "a flag with an expiry" to a genuine stateful aggregate. Administration's evaluation engine and admin console consume whichever shape exists without needing to change.

**Data Classification.** A fixed, developer-maintained, closed-but-extensible enumeration (extended only when a genuinely new class of data appears, never at runtime). Starting vocabulary, deliberately coarse: **Identity, Financial, Academic, Operational, Audit.** Every model declares exactly one classification, enforced by an architecture test (mirroring Sprint 2.4's Identity Maintenance contract-declaration test — a negative-tested check, not a trusted convention). Retention policy targets a classification by default; a documented per-model override remains possible for a genuine exception.

**Import/Export.** Format-agnostic by construction: a source-adapter contract (CSV first; Excel/JSON/XML/API-based later, each an additive implementation) yields a normalized record stream to one format-agnostic mapping/validation/error-collection engine and target writer. The adapter contract is generator-shaped from the CSV implementation onward specifically so paginated, asynchronous API-based imports fit later without a redesign.

**Open, unresolved by ADR-0011:** whether admin-managed lookup/reference tables (Nationality, Religion, Language) live in Core or Administration; whether Administration becomes one literal module namespace or stays a conceptual grouping across sibling Foundation modules — deferred to Administration's own implementation-planning pass.

## Communications (ADR-0012)

A new Domain module — not previously named anywhere in the Blueprint, a genuine extension of the fixed Domain module list, the same weight as adding a tenth Domain module. Exists because audience-broad messaging (a school-wide announcement, a term-start campaign) needs business-aware audience resolution that Notifications, by its own frozen shallow/generic charter, must never perform itself.

**Owns:** Broadcast (a resolved audience + one message), ScheduledMessage (a Broadcast/template with a future delivery time), Campaign (a named batch sharing one audience and purpose — enforcing that invariant the way Invoice enforces total = sum of lines). None of these render or deliver — every one calls Notifications.

**Audience resolution.** Composes each Domain module's public service only, via a mirrored `Audienceable` contract per module (parallel to Finance's `Billable`) — never raw table access. This is the mechanism that keeps Communications from becoming an uncontrolled second entry point into every other module's data.

**Transactional messages bypass Communications entirely.** A one-to-one, business-triggered message (an admission decision, a fee reminder, a grade posted) is dispatched directly by its originating module to Notifications via a queued listener on that module's own domain event. Communications exists only for audience-broad messages with no single natural triggering record.

**OTP is fully out of scope for Communications** — Identity calls Notifications directly and synchronously (time-sensitive, never queued), never touching Communications' audience machinery.

**Open, unresolved by ADR-0012:** whether a permanent delivery failure flows back to the originating business workflow (would need a new reverse-direction event contract); whether message scheduling is a generic Notifications capability or per-module; whether running a Campaign is itself licensable (ties to ADR-0011's promotion trigger); the per-case judgment of routing a business event (e.g. `InvoiceOverdue`) direct-to-Notifications vs. through Communications when run as a batch.

## The Notification Engine's Channel/Provider architecture (ADR-0013)

**Channel vs. Provider.** A Channel (Email/SMS/WhatsApp/Push/In-App) is a contract; a Provider is a vendor's implementation. The Engine and every business module request only "send Email/SMS/WhatsApp/Push/In-App," never a named vendor.

**Provider structure.** One concrete Provider class per channel contract, even for a vendor spanning multiple channels — e.g. `TwilioSmsProvider` and `TwilioWhatsAppProvider` each implement one channel contract, sharing an internal `TwilioClient` (credentials + HTTP transport, not itself a Provider). Keeps each Provider single-responsibility and independently testable while avoiding duplicated credential configuration.

**Interface segregation.** Primary contracts stay to their minimal shape (`send()`); optional capabilities (delivery receipts, two-way reply) are separate, optionally-implemented interfaces, checked via `instanceof` only when a real need arises.

**WhatsApp's bounded exception.** The one channel where vendor differences are business-semantic: official-API-backed providers (Meta, Twilio) enforce pre-approved templates and a 24-hour session window for free-form replies; unofficial providers typically don't. The WhatsApp Provider contract exposes which rules apply; Templates carry a per-provider vendor-template-reference for this channel only. Deliberately the single, documented crack in "the Engine never knows about vendors."

**In-App is not vendor-swappable.** Persistence is an Engine-owned write, no vendor to swap. Only its optional real-time layer maps onto a Provider-shaped concept, and this project already has that abstraction via its existing Broadcasting driver config (Reverb/Pusher/Ably) — no bespoke In-App Provider is to be built.

**Configuration — future-ready, single-provider in behavior.** Every channel declares `default`/`failover`/`routing`; only `default` resolves anything in this release:

```yaml
sms:
  default: twilio
  failover: []
  routing: []
```

**Resolution mechanism.** A Laravel Manager (config-key-driven, `extend()`-able) — the same native mechanism already governing this codebase's `config/filesystems.php` and `config/broadcasting.php` — no bespoke resolver.

**Testing.** A Null/Log Provider per channel is mandatory from the first implementation — no test reaches a real vendor.

**Secrets and scope.** Provider credentials are encrypted config/secrets, scoped per deployment (per ADR-0006's dedicated-instance model), administered via Administration Platform's provider-configuration UI — never a plaintext Settings row.

**Vendor SDK isolation.** A vendor SDK type is importable in exactly one place: that vendor's own Provider implementation. No Channel contract, Engine internal, or business module may reference a vendor SDK type.

**Deferred, non-normative — Provider observability.** Providers may eventually expose Health checks, Metrics, Latency, Delivery statistics — consumed only by Administration's monitoring dashboards, never the Engine's send path. Not part of the first implementation; each, if built, is its own optionally-implemented capability interface following the same interface-segregation principle above.

## What none of this is yet

No code, no migrations, no service classes exist for Administration Platform, Communications, or the Notification Engine's Provider implementations. This document and ADR-0011/0012/0013 record a frozen *architecture*, not a built feature. Implementation begins only once each is given a scheduled sprint via a dedicated planning pass, per `docs/IMPLEMENTATION_PLAYBOOK.md`'s own rule that phases beyond the immediate sequential chain get "full sprint planning deferred to a dedicated pass per phase."
