# BUS-0020: Academic / Learning Boundary — Subject vs. Course Template, Content Ownership

**Status:** 🟢 Accepted (naming/ownership boundary) — the deeper claim that Curriculum itself splits into Subject Sequencing (Academic) vs. Curriculum Content (Learning) remains 🟡 Proposed, not decided by this ADR

**Date:** 2026-07-22

**Related Domains:** Academic, Learning

**Related ADRs:** BUS-0001, BUS-0018

## Context

The general Academic review proposed splitting "Curriculum" into Subject Sequencing (Academic) vs. Curriculum Content (Learning) as a 🟡 Proposed finding. The subsequent Subject Model review independently confirmed a boundary from the opposite direction — Academic's catalog entity vs. Learning's content entity — while resolving the "Course" naming collision between the two domains.

## Problem

Where exactly does Academic's responsibility end and Learning's begin, for anything touching Subjects/Courses?

## Alternatives Considered

- **One shared "Course/Subject" entity spanning both catalog metadata and pedagogical content** — rejected. Different lifecycles (catalog changes at curriculum-reform cadence; content changes continuously as teachers author material) and different owning concerns.
- **Learning owning the catalog/credit/prerequisite data**, since it already has "Course" — rejected. That's a registrar concern (Academic's), not a content-delivery concern.

## Final Decision

Academic owns **Subject** (catalog, credit hours, prerequisites, equivalence, retirement — BUS-0018). Learning owns **Course Template** (content, lessons, homework, quizzes — BUS-0001) and may reference a Subject as the catalog entity its content is built for, but never duplicates or overrides Academic's catalog data. The word "Course" is never the schema/API name on Academic's side; it may appear only as Learning's own entity name, or as a display label. The fuller claim — that Learning's responsibility extends to sequencing recommendations tied to learning objectives, splitting Curriculum itself into two halves — remains 🟡 Proposed and undecided; only the Subject/Course Template boundary is settled here.

## Why This Decision Was Chosen

Resolves two independently-arrived-at findings from separate reviews into one consistent boundary, rather than leaving two overlapping-but-not-identical statements about the same seam sitting in two different documents.

## Consequences

Easier: Academic and Learning can be implemented independently against a settled contract (Subject ID as the join point). Harder: nothing new — this closes ambiguity rather than opening a tradeoff.

## Future Implications

Whenever Learning's v1→v3 retrofit happens, the Curriculum-Content-vs-Subject-Sequencing question should be resolved properly rather than left Proposed indefinitely.

## Traceability

- **Business requirement:** no domain may own or duplicate another domain's authoritative data — an existing platform-wide principle, applied here specifically.
- **Introduced in:** general Academic review (Proposed) + Subject Model review (confirmed from the Learning-boundary direction).
- **Depended on by:** BUS-0018, any future Learning v1→v3 retrofit.
