# BUS-0007: Learning Domain Renamed from LMS → Learning Ecosystem → Learning Intelligence Platform (Concept Accepted, Acronym Open)

**Status:** 🟡 Proposed (naming direction accepted; final short name/acronym unresolved)

**Date:** 2026-07-22

**Related Domains:** Learning (Domain 8 in `docs/BUSINESS_BLUEPRINT.md`, currently still named "LMS (Distance Learning)" there — not yet updated)

**Related ADRs:** BUS-0001 through BUS-0006 — all of them are why the name needed to change.

## Context

The domain was originally scoped and documented as "LMS (Distance Learning)." Across the deep-review and AI-first-entities discussions, the domain's actual described responsibilities grew to include content authoring workflow, competency tracking, adaptive learning substrate, AI-mediated mastery estimation, and cross-domain event consumption — materially broader than course/content management.

## Problem

Does "LMS" still describe what this domain does, and if not, what should replace it?

## Alternatives Considered

- **Keep "LMS"** — rejected: none of the AI-first entities (Concept Graph, Continuous Mastery, Learning Intervention) fit inside a "course management system" framing; the name actively undersells and mis-scopes the domain relative to what's now documented.
- **"Learning Ecosystem"** — proposed and accepted for one turn. Rejected on further review: doesn't make explicit that this domain *consumes* AI Platform's capability rather than owning it, which is a real, binding architectural boundary (BUS-0003) the name should reflect.
- **"Learning Intelligence Platform (LIP)"** — proposed. The underlying concept — the domain's job is understanding, measuring, supporting, and improving the learning process, with institutional pieces (courses, sections, billing, official records) staying inside the same system — accepted as correct and precise, and correctly signals the AI Platform/Learning boundary. The three-letter acronym "LIP" itself was flagged as an awkward, casual-sounding initialism for a serious platform component and left unresolved.

## Final Decision

The conceptual rename to "Learning Intelligence Platform" is accepted. The specific short name/acronym is **not** decided — "LIP" is explicitly flagged as unresolved, tracked in Open Architecture Questions, not silently adopted by default.

## Why This Decision Was Chosen

Naming precision has mattered throughout this document — a prior collision ("Core Services" vs. the already-meaningful "Core" in the backend architecture) was caught and corrected the same way. The conceptual name should be settled with the same rigor as any other architectural decision, and an acronym shouldn't become the default final answer just because it was the most recent one proposed.

## Consequences

Easier: the domain's documented scope now has a name that doesn't undersell it. Harder: `docs/BUSINESS_BLUEPRINT.md`'s Domain 8 heading still reads "LMS (Distance Learning)" and needs updating once a final short name is settled — not done yet, tracked as an Open Architecture Question, not silently left inconsistent.

## Future Implications

Whatever short name is finally chosen should be applied retroactively to Domain 8's heading and every cross-reference to it elsewhere in the document (Domain 3's Academic gradebook integration note, for one).

## Traceability

- **Business requirement:** none directly — this is a documentation-accuracy correction following from BUS-0001 through BUS-0006 collectively broadening the domain's real scope.
- **Introduced in:** "Learning Ecosystem" proposed in the AI-first entities follow-up turn; "Learning Intelligence Platform" proposed in the same turn as BUS-0002 through BUS-0006.
- **Depended on by:** nothing structurally — purely a naming/documentation consistency item — but every ADR in this file references "Learning" as the domain name pending this resolution.
