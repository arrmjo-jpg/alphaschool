# BUS-0016: Domains Split Into Folders Only Past a Size Threshold, Not Universally Up Front

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** all — this governs the documentation structure itself, not any one domain's content

**Related ADRs:** none — this is a documentation-architecture decision, not a business-domain decision, but is tracked in the same Decision Log since it's an accepted, binding rule going forward

## Context

Following the file-per-domain refactor (single markdown files under `docs/business-domains/`), it was proposed that every domain be further split into its own folder (`README.md`, `entities.md`, `workflows.md`, `permissions.md`, `reports.md`, `integrations.md`, `decisions.md`, `diagrams/`), on the reasoning that domains will keep growing and a single file per domain won't hold up at 30–40 domains.

## Problem

Should every domain be restructured into a multi-file folder now, preemptively, or only when a specific domain actually needs it?

## Alternatives Considered

- **Restructure all domains into folders now** — the proposal as given. Rejected: checked against actual file sizes, the largest and most complex domain built so far (Inventory, full v3 template, its own entire Stock Management ledger sub-layer) is 150 lines. Every other domain is under 100. Splitting content this size into 7 files produces sub-files of 15–20 lines each — worse for navigation, not better, and applies a heavier structure uniformly to domains (Administration, Admissions, HR) that will plausibly never need it.
- **Never split, keep single files indefinitely regardless of size** — rejected: a domain that genuinely accumulates deep sub-designs (Learning, with Concept Graph, Competency, Rubrics, Content Workflow, and Learning Object Repository all pending) could realistically outgrow a single file's readability once its full retrofit lands.
- **A size threshold that triggers the split per-domain, only when actually crossed** — accepted.

## Final Decision

Domains remain single markdown files under `docs/business-domains/` until a specific domain's file exceeds roughly 250–300 lines, at which point *that domain* — not all of them — is promoted to its own folder. When a domain is split, its sections map onto the existing v3 template rather than a new taxonomy: `README.md` (Purpose/Responsibilities/Business Capabilities/Commercial Differentiators), `entities.md` (Submodules/Master Data/Settings), `workflows.md` (Workflows/Domain Events/Automation Opportunities), `ai.md` (AI Opportunities — only for domains with real AI design weight), `integrations.md` (Provider Slots/Public APIs/Extension Points/Mobile Features), `reports.md` (Dashboards/Reports/KPIs), `permissions.md` (Security Classification/Permissions/Audit Requirements/Data Ownership), `decisions.md` (Related ADRs + open items), `diagrams/` (new, nothing exists yet for any domain).

## Why This Decision Was Chosen

This is the same "don't build the general mechanism before a real need proves it's warranted" discipline already applied repeatedly elsewhere in this project — the Workflow Engine stayed deferred until a real funnel needed it; Program vs. Module is decided per capability, not applied as a blanket default. Applying a uniform 7-file structure to every domain regardless of actual size would be the identical mistake in documentation tooling instead of code.

## Consequences

Easier: no domain carries structural overhead it doesn't need; the threshold is mechanically checkable (a line count), not a judgment call revisited every time someone feels a file is "getting big." Harder: this requires someone to actually notice when a domain crosses the threshold, rather than it being enforced automatically — worth a periodic size check as domains accumulate, not a one-time decision.

## Future Implications

Learning is the domain most likely to cross this threshold first, once its still-pending v1→v3 retrofit absorbs Concept Graph, Competency, Rubrics, Content Workflow, and Learning Object Repository — that retrofit is the natural moment to apply the folder split, not before. Academic, starting next, remains a single file like every other domain until it actually earns otherwise.

## Traceability

- **Business requirement:** documentation must remain navigable and reviewable as the platform grows toward 30–40 domains.
- **Introduced in:** the same turn that reviewed the file-per-domain refactor and proposed going one level deeper.
- **Depended on by:** whichever domain first crosses ~250–300 lines — expected to be Learning, at its v1→v3 retrofit.
