# ADR-0012: Communications as a Thin Aggregator Domain Module

**Status:** Accepted

**Date:** 2026-07-12

## Context

`docs/DOMAIN_BLUEPRINT.md` §1 names **Notifications** as a Foundation module ("Templated, bilingual, multi-channel notification delivery and preference management") and describes Notification as a shallow, generic coordination pattern, the same category as Approval — never a domain-rich concern. Nowhere does the Blueprint name a "Communications" module, in either the Foundation or Domain layer tables, nor in the "named by sponsor, not yet designed" table (Maintenance, CRM). Introducing it is therefore a genuine extension of the fixed Domain module list, the same weight as adding a tenth Domain module beside Admissions/Academic/Finance/HR/Inventory/Library/Transportation/LMS/Reporting — not a reorganization of something already agreed.

The gap Communications fills: audience-broad, cross-module messaging (a school-wide announcement, a term-start campaign to all parents in a branch) requires business-aware audience resolution spanning People/Academic/HR data. Notifications, by its own frozen charter, must stay domain-agnostic — it renders and delivers, it does not decide who receives what or why. Something has to own that audience-resolution business logic, and it cannot be Notifications without violating the Blueprint's own shallow/generic vs. domain-rich distinction (§15).

## Decision

Introduce **Communications** as a Domain module, deliberately thin, mirroring the aggregator exception the Blueprint already grants Finance ("needs data from many modules... but still must go through each module's public service... never raw table access"). Communications owns exactly three conceptual aggregates: **Broadcast** (a resolved audience + one message), **ScheduledMessage** (a Broadcast or template with a future delivery time), and **Campaign** (a named batch of Broadcasts/ScheduledMessages sharing one audience and purpose — enforcing that invariant the same way Invoice enforces total = sum of lines). None of these render or deliver anything themselves; every one of them calls Notifications to actually send.

**Audience resolution contract:** Communications may only compose each Domain module's public service — never raw table access — via a mirrored `Audienceable` contract per module that wants to expose a segment (e.g., People exposing "all Guardians in Branch X," Academic exposing "all Students in Grade 5"), the same discipline Finance's `Billable` contract already established. This is the specific mechanism that keeps Communications from becoming a second, uncontrolled entry point into every other module's tables.

**Transactional messages never route through Communications.** A one-to-one, business-triggered message (an admission decision, an invoice reminder, a grade posted) is dispatched directly by its originating Domain module to Notifications via a queued listener on that module's own domain event — the same event-driven discipline already governing every other cross-module reaction in this system. Communications exists solely for audience-broad messages with no single natural triggering record.

**OTP is explicitly out of scope for Communications.** OTP mechanics (generate, store, verify, expire) remain entirely Identity's concern (`StepUpAuthenticationService`, built Sprint 2.2); OTP *delivery* is a direct, synchronous call from Identity to Notifications for one verified `Contact` — time-sensitive, never queued, and never touching Communications' audience-resolution machinery.

## Consequences

The Notification Engine (Foundation) stays genuinely domain-agnostic, exactly as its Blueprint charter requires; Communications (Domain) carries all business-aware audience logic, and depends on Notifications the correct direction (Domain → Foundation), never the reverse. Every future Domain module that wants to run a campaign or broadcast implements one `Audienceable` method rather than Communications needing bespoke knowledge of that module's schema. Four decisions from the originating review remain explicitly open, not resolved by this ADR: whether a permanent delivery failure flows back to the originating business workflow (would require a new reverse-direction event contract); whether message scheduling is a generic Notifications capability or managed per-module; whether running a Campaign is itself a licensable capability (ties to ADR-0011's Licensing promotion trigger); and the precise boundary case for events like `InvoiceOverdue` (direct-to-Notifications for a single invoice vs. routed through Communications when run as a batch campaign) — each is a real per-case judgment call to make when the triggering module is actually built, not a rule this ADR can fully specify in advance.

## Alternatives Considered

- **Fold all audience-broad messaging into Notifications directly.** Rejected — would force Notifications to become domain-aware (knowing what a "Guardian" or "Grade 5" is), directly violating its own frozen Blueprint charter as a shallow, generic coordination concern.
- **Let each Domain module build its own campaign/broadcast logic independently (Finance runs its own reminders, Admissions runs its own marketing sends, etc., with no shared aggregator).** Rejected — duplicates audience-resolution and batch-management logic per module, the same "per-module reinvention" failure mode Administration Platform (ADR-0011) exists to prevent, applied here to messaging specifically.
- **Absorb Communications entirely into Administration Platform.** Rejected — Communications' aggregates (Broadcast, ScheduledMessage, Campaign) are business-rich (composing People/Academic/HR data via audience resolution), which is Domain-shaped per the Blueprint's own layering rule, not a cross-cutting governance concern like Settings or Custom Fields.

## References

`docs/DOMAIN_BLUEPRINT.md` §1, §2, §14, §15. `docs/adr/0011-administration-platform-bounded-context.md`. `docs/adr/0013-channel-provider-separation-for-notification-engine.md`. `docs/adr/0008-user-login-identifiers-vs-person-contacts.md` (governs which `Contact` any dispatch, including Communications', ultimately resolves to). Raised during a dedicated Communications architecture review, 2026-07-12, before any Sprint 2.5+ implementation and before Communications occupies a scheduled sprint slot.
