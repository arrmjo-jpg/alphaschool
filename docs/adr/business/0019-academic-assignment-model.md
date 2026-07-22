# BUS-0019: Academic Assignment Model — Teacher Assignment, Homeroom, Coordinator, Effective Dating

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Academic, HR (Position vs. academic-authority-scope split), Students (Homeroom/Section)

**Related ADRs:** BUS-0017 (Academic Department/Coordinator scope), BUS-0018 (Subject Offering is what Teacher Assignment attaches to)

## Context

Raised as the strongest finding of the general Academic architectural review. Teacher, Homeroom, and Coordinator scope had been implied as mutable current-value fields, which loses history the instant a teacher changes subject, section, or role mid-year — a problem this project has already paid for once (Employment/Enrollment) and corrected.

## Problem

Should Teacher/Homeroom/Coordinator assignment be a field on Employee or Section, or a full effective-dated aggregate — and if the latter, does it reuse the existing Assignment Engine pattern or need a new one?

## Alternatives Considered

- **Mutable current-value fields** (e.g., `section.homeroom_teacher_id`) — rejected. Overwrites history the instant a mid-year change happens; the platform has already corrected this exact mistake once in Enrollment's own design and shouldn't reintroduce it here.
- **A new, Academic-specific assignment mechanism**, separate from the existing Assignment Engine — rejected. Position vs. Role vs. Assignment (with effective-dating) is an already-established, proven project pattern; there's no reason to build a parallel one for a structurally identical problem.

## Final Decision

Teacher Assignment, Homeroom assignment, and Coordinator/Department-Head academic-scope assignment are all modeled as effective-dated Assignment aggregates, in the same category as Enrollment and Employment (period-scoped, not Master Data), reusing the existing Assignment Engine pattern directly. Coordinator/Department-Head assignment specifically carries HR's Position identity plus Academic Department's academic-authority scope — the mechanism BUS-0017 resolves.

## Why This Decision Was Chosen

A direct, unmodified reuse of a pattern this project had already built and proven for a structurally identical problem (Employment, Enrollment) — the strongest kind of design decision available, since it costs nothing new to validate.

## Consequences

Easier: a teacher's full assignment history (which sections, which subjects, which years) becomes queryable for free; a mid-year Homeroom change is a normal, auditable event rather than a silent overwrite. Harder: every read path that today assumes "the current teacher of this section" as a simple field needs to resolve through the Assignment Engine's "current as of date" query instead.

## Future Implications

None beyond ordinary reuse — this closes an architectural gap rather than opening a new tradeoff.

## Traceability

- **Business requirement:** preserve accurate historical academic records (who taught what, when) for transcripts, audits, and reporting.
- **Introduced in:** the general Academic architectural review.
- **Depended on by:** BUS-0017 (Coordinator/Department-Head scope), BUS-0018 (Subject Offering's teacher assignment), any future Timetabling work.
