# BUS-0011: Course Template / Course Offering / Course Staff / Sessions Base Structure Accepted, With Named Unresolved Sub-Issues

**Status:** 🟢 Accepted (base structure) — three sub-issues remain 🔴 unresolved, listed below, not silently closed by this ADR

**Date:** 2026-07-22

**Related Domains:** Learning

**Related ADRs:** BUS-0001 (versioning, a correction to this same structure)

## Context

The Course Template (content only) / Course Offering (a scheduled run — Teacher, Academic Year, Enrollment Rules, Audience, Pricing, Certificates, Meeting Provider) split, along with Course Staff roles (Lead Instructor, Instructor, TA, Content Author, Reviewer, Guest Lecturer) and Sessions (Zoom/Meet/Teams/Custom), was presented as the existing/proposed core model and reviewed critically across several turns, on the explicit instruction to assume the base structure correct and find flaws rather than redesign it.

## Problem

Is the base Template/Offering/Staff/Sessions structure itself an accepted decision, or does it remain an open proposal — and which specific flaws found during review are actually resolved versus merely identified?

## Alternatives Considered

Not applicable in the usual sense — this ADR's purpose is to formally record something that was *treated* as accepted throughout multiple design turns but never actually written down as a decision, which is itself the documentation gap this ADR closes.

## Final Decision

The base structure is accepted: Course Template (versioned per BUS-0001) as content-only; Course Offering as a scheduled run referencing Teacher, Academic Year, Enrollment Rules, Audience, Pricing (referencing Accounting, not reimplementing it), Certificates (referencing the Document Engine, not reimplementing it), Meeting Provider; Course Staff as a real role set, not a single "Teacher" field; Sessions as the schedulable unit a Meeting Provider is attached to.

**Explicitly not resolved by accepting the base structure — three real, named flaws remain open, tracked here rather than lost:**
1. **Enrollment is single-valued per Offering** and cannot express a realistic mixed audience (Grade auto-enrolled, Parents requiring approval, Public paying, simultaneously, for the same Offering).
2. **Teacher is single-cardinality** — co-teaching, TA grading delegation, and guest lecturers have no home.
3. **Meeting Provider is Offering-level, not session-level** — a recurring weekly class with per-session meeting instances has no way to express distinct links/times per session.

## Why This Decision Was Chosen

Formalizing the base structure as accepted (rather than leaving it implicitly assumed) lets the three real flaws be tracked as precise, scoped sub-issues against a stable foundation, instead of the whole structure remaining an ambiguous, never-quite-decided proposal that any future conversation could re-litigate from zero.

## Consequences

Easier: future Learning design work has a clear, named foundation to build on. Harder: the three flaws above are real product gaps that need their own resolution before Offering-level enrollment/staffing/session design is considered complete — they are not closed by this ADR, only precisely named instead of left as vague critique-turn findings.

## Future Implications

Any future ADR resolving per-audience-segment enrollment rules, multi-instructor cardinality, or per-session meeting instances supersedes the relevant part of this one.

## Traceability

- **Business requirement:** support School LMS, Public/Paid/Free Courses, and Training tracks on one shared Offering structure.
- **Introduced in:** the original Learning domain write-up (pre-dating formal ADR tracking); reviewed in the "AlphaSchool ERP – Deep Architecture Review (Learning/Course design)" critique turn, which found the three sub-issues above without the base structure itself ever being disputed.
- **Depended on by:** any future audience/enrollment, staffing, or session-scheduling redesign.
