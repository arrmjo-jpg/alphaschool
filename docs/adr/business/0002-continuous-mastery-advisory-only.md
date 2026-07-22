# BUS-0002: Continuous Mastery Is Advisory Only — Official Grade Is Never Auto-Derived From It

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Learning (formerly LMS)

**Related ADRs:** BUS-0003 (AI Decision as a unified platform primitive — the mechanism this ADR's advisory relationship is implemented through)

## Context

A "Continuous Mastery Model" was proposed as an AI-first alternative to discrete Grades — a continuously-updated, probabilistic mastery estimate per learner per learning objective, fed by every interaction rather than only formal assessments. The first draft of this proposal described Grade as "a periodic snapshot derived from" Mastery.

## Problem

Should the AI-computed Mastery estimate be the source of truth Official Grade is mechanically derived from, or an advisory signal a human grading process may or may not use?

## Alternatives Considered

- **Grade automatically derived from Mastery** — the first-draft framing. Rejected on review: schools operate under official grading scales, institutional policy, and in many jurisdictions Ministry/regulatory requirements that an AI mastery estimate has no obligation to satisfy and shouldn't be conflated with. "Derived from" implies an automatic pipeline even when described as a human-facing summary.
- **Mastery and Grade as two entirely unrelated, non-interacting numbers** — rejected as wasteful; it discards the genuine value of a continuous signal informing a human decision.
- **Mastery as advisory input to a human-owned grading decision** — accepted.

## Final Decision

`AI Mastery → Recommendation`, never `→ Official Grade`. The teacher's grading action remains the sole path to an official grade of record. The AI mastery estimate may be surfaced to the teacher as a suggestion or input, but no automatic pipeline exists from Mastery to Grade.

## Why This Decision Was Chosen

Two reasons, not one. First, it extends the "AI proposes, human commits" discipline already binding elsewhere in this project (Health Clinic's AI Diagnosis Provider, Smart Campus's AI-assisted identity matching) to cover the *production of an official record*, not only to consequential actions — the earlier framing was inconsistent about this specific gap. Second, and independently, Official Grade has real regulatory obligations (grading scales, retention, potential Ministry reporting) that have nothing to do with an AI model's confidence and shouldn't be coupled to it.

## Consequences

Easier: no regulatory or trust exposure from an AI estimate silently becoming an official academic record. Harder: the platform must maintain two related but distinct concepts (Mastery, Grade) rather than one, and the UI must make the advisory-not-authoritative relationship obvious to teachers, not implicit.

## Future Implications

Any future AI capability that could plausibly produce an "official" record in any domain (not only Learning) should be checked against this same precedent before being allowed to write directly to a record of consequence.

## Traceability

- **Business requirement:** "AI as a first-class capability" while preserving official institutional grading and regulatory compliance.
- **Introduced in:** the "Forget Moodle, Forget Canvas" AI-first entities discussion, refined in the immediate follow-up turn.
- **Depended on by:** BUS-0003.
