# BUS-0006: AI-Specific Consent Belongs to a Privacy/Consent Domain That Doesn't Yet Exist; Learning Only Queries It

**Status:** 🔵 Deferred (decision on ownership accepted; the Privacy domain itself is unbuilt)

**Date:** 2026-07-22

**Related Domains:** Learning; a not-yet-built Privacy/Consent domain

**Related ADRs:** none

## Context

Telemetry-level behavioral monitoring for AI purposes (see BUS-0005) raises consent questions distinct from ordinary academic record-keeping — many jurisdictions treat AI-driven decisions affecting minors, and the use of a child's behavioral data to personalize or train a model, as requiring separate, specific consent.

## Problem

Should AI-specific consent be modeled as a Learning-domain concept, or does it belong elsewhere?

## Alternatives Considered

- **AI consent as a Learning-domain setting/flag** — rejected: folds a distinct legal/privacy concern into a domain that has no business owning consent governance, and would need to be reinvented by every other AI-touching domain independently.
- **No explicit AI consent modeling, rely on general enrollment consent** — rejected: general enrollment consent doesn't cover the specific legal question of AI-driven personalization/training use of a minor's behavioral data in the jurisdictions that regulate it.
- **A dedicated Privacy/Consent domain, Learning (and any other AI-touching domain) queries it before acting** — accepted.

## Final Decision

AI-specific consent is owned by a Privacy/Consent domain, tied to the Learner Profile. Learning queries this domain before enabling AI-personalized features for a given learner; it does not store or manage consent itself.

## Why This Decision Was Chosen

Same owner/consumer discipline already used throughout this document (Emergency Coordination, LMS-to-Academic grade sync, Inventory-to-Assets cross-reference) — a cross-cutting concern gets one owner, everyone else consumes it, nobody duplicates it.

## Consequences

Easier: consent logic lives in exactly one place, auditable once rather than reimplemented per AI-touching domain. Harder: this decision has a real, unresolved dependency — **the Privacy/Consent domain itself does not exist yet anywhere in this document.**

## Future Implications

This is not a new gap — Data Privacy / Consent Management was already flagged as a blind spot during the original Platform Scope discovery pass, long before any AI-specific design work began. What's changed is that it now has a concrete, forcing consumer (Learning's AI features cannot ship responsibly without it), moving it from "a noted gap" to "a blocking dependency." Tracked in Open Architecture Questions until the Privacy domain is actually designed.

## Traceability

- **Business requirement:** legal/regulatory consent requirements for AI use of minors' behavioral data.
- **Introduced in:** the original Platform Scope discovery pass (blind spot noted, unbuilt); elevated to a blocking dependency in the "Forget Moodle, Forget Canvas" AI-first entities discussion.
- **Depended on by:** BUS-0002, BUS-0004, BUS-0005 — none of Learning's AI features can ethically/legally ship without this domain existing.
