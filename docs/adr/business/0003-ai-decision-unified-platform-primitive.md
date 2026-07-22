# BUS-0003: Reasoning Trace, AI Provider Version, and Human Override Are One Unified AI Platform Primitive, Not Learning-Specific Entities

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Learning, Health Clinic (retroactive), Smart Campus & Physical Security (retroactive), Administration/Platform Services (AI Platform, wherever it is formally documented)

**Related ADRs:** BUS-0002 (the Mastery/Grade relationship this mechanism implements)

## Context

Three concepts were proposed as new Learning-domain entities: Reasoning Trace (why an AI decision was made), AI Provider Version (which model/provider/prompt produced it), and Human Override (a record of a human rejecting an AI suggestion, with reason). On review, these were judged to not be Learning-specific at all.

## Problem

Should Reasoning Trace, AI Provider Version, and Human Override be modeled as three (or one) Learning-domain entities, or as a shared platform capability every AI-touching domain references?

## Alternatives Considered

- **Three separate Learning-domain entities**, as first proposed. Rejected: this domain isn't the only one that already needed this — Health Clinic's AI Diagnosis Provider and Smart Campus's AI-assisted identity matching were each already written with their own ad-hoc "human must confirm" language, describing the same underlying discipline three different ways.
- **One combined entity, but still Learning-owned**, with other domains expected to duplicate it independently. Rejected: recreates exactly the kind of domain reimplementing shared infrastructure this project has corrected multiple times already (Number Generator, Document Engine).
- **One generic `AIDecision` primitive owned by AI Platform, referenced by any domain** — accepted.

## Final Decision

Reasoning Trace, AI Provider Version, and Human Override collapse into one generic record — provisionally named `AIDecision` — owned by AI Platform (Provider, Model, Prompt Version, Confidence, Reason, and an optional Override sub-record). It carries a polymorphic reference back to whichever domain-specific record it concerns (a Learning Objective, a Patient record, an Access Point), the same shape already proven for Stock Movement's reference to its causing business record and a Journal Entry's reference to its source document. Every domain *declares a use of* this shared capability; none reimplement it.

## Why This Decision Was Chosen

This is the same registration-pattern discipline already used throughout the platform (`DeclaresSettingsSchema`, `DeclaresProviderSlots`) — a domain declaring a use of shared infrastructure, not owning a parallel copy of it. It also converts a scattered, inconsistently-worded "AI must be supervised" principle (stated three different ways across three domains) into one checkable, reusable structure.

## Consequences

Easier: any future AI-touching domain (this platform explicitly expects more) gets audit, explainability, and override tracking for free by referencing this primitive, rather than re-deriving the same discipline from scratch. Harder: Health Clinic and Smart Campus's existing prose descriptions of their own AI-confirmation behavior are now technically superseded and owe a retroactive correction pass in `docs/BUSINESS_BLUEPRINT.md` to reference this primitive explicitly instead of describing their own ad-hoc version of it — **not yet done, tracked as an Open Architecture Question.**

## Future Implications

Every future AI Opportunity section written for any domain going forward should reference `AIDecision` directly rather than re-describing "human must confirm" in that domain's own words.

## Traceability

- **Business requirement:** "AI as a first-class capability, not an integration," while keeping every AI-influenced decision auditable and human-supervised.
- **Introduced in:** the "Forget Moodle, Forget Canvas" AI-first entities discussion; generalized in the immediate follow-up turn.
- **Depended on by:** BUS-0002; retroactively, Health Clinic §AI Opportunities and Smart Campus §AI Opportunities in `docs/BUSINESS_BLUEPRINT.md` (correction pending, see Open Architecture Questions).
