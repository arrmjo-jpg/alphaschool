# ADR-0024: Shared Role Pattern (Actor ↔ Governed Resource)

**Status:** 🟢 Accepted (promoted from Proposed, 2026-07-26). Learning's Blueprint satisfied all three Validation Plan conditions on direct inspection: no structural change to the pattern, no new relationship metadata introduced outside it, Learning-specific business data (Progress, Grades, Certificates) stayed Learning-owned throughout. See Validation Plan below for the condition-by-condition record.

**Date:** 2026-07-26

## Context

Learning's Participation Model question (raised while attempting to write the Learning Domain Blueprint) surfaced that "Person + Target + Capacity + effective period" was already independently implemented three times — Employment (HR), Enrollment (Students), Teacher/Homeroom/Coordinator Assignment (Academic, BUS-0019) — without ever being named as a shared pattern. A generalization was proposed, then deliberately stress-tested against every Person-to-X relationship findable across current and planned domains, specifically trying to disprove it rather than confirm it. It survived in a narrower form than first proposed, and was refined twice based on what the counterexamples revealed.

## Terminology

**Actor**: an identity that can intentionally hold a governed role within the platform. The current platform recognizes `Person` as the primary Actor. Additional Actor types (Visitor is the leading candidate; a Service Account, Device, or Agent are explicitly *not* pre-approved by this ADR) require their own explicit ADR before participating in this pattern — this term is deliberately not left open enough for a future author to assume a robot or a device qualifies without a deliberate architectural decision.

**Governed Resource**: anything carrying attached policy, responsibility, or an independent lifecycle that an Actor can hold a role against — a Position, a Program, an Item, a Group. Not "institutional" in any narrow sense (a Library Book and a Committee both qualify; neither is an institution) — the defining property is that the resource is *governed*, not that it is owned by a formal organizational unit.

## Decision

**1. Naming and semantic definition.** The pattern is **Actor ↔ Governed Resource**: an Actor (Person is the primary and only currently-confirmed instance; other independent identity types — Visitor is the leading candidate — may qualify later if proven independently, without requiring this pattern's shape to change) holds a **Capacity** with respect to a **Governed Resource** — anything carrying attached policy, responsibility, or lifecycle (a Position, a Program, an Item, a Group) — for an **effective period**, in a **lifecycle state**.

The generalization from "Person" to "Actor" and from "institutional Target" to "Governed Resource" is not cosmetic. The narrower framing (Person, institutional Target) is precisely what left Visitor and Access Credential unresolved in the stress test: Visitor was deliberately built outside Person (BUS-0022 — a temporary, revocable identity doesn't belong in the same substrate as permanent ones), and Library Book/Item/Committee/Club are not institutions in any meaningful sense, yet clearly belong in this pattern's scope. The corrected terms remove constraints that were never load-bearing, without altering any already-confirmed fit.

**2. Confirmed fits (evidence).** Employment, Enrollment (shape only — its promote/repeat/withdraw transition logic is governed by Business Rules, `ADR-0020`, living outside this pattern), Teacher/Homeroom/Coordinator Assignment, Asset Custody (Inventory), Library Borrowing, Committee/Club Membership (plausible, unbuilt), Clinic/Front-Desk/Security staffing assignments (Health Clinic, Reception, Smart Campus).

**3. Decision Criteria — the checklist for whether a new relationship uses this pattern.** This applies *after* confirming the relationship is genuinely Actor-to-Governed-Resource, not Actor-to-Actor — that distinction is what excludes Guardian/Parent (Section 4 below), and it is a definitional gate, not one of the six criteria that follow. Once past that gate, a relationship belongs in this pattern only if all six hold:

- The relationship is ongoing, not a point-in-time event.
- It carries a role or responsibility, not merely an identifying reference.
- It has an independent lifecycle of its own, separate from either party's.
- It needs a start/end date or a lifecycle state.
- The pattern must be capable of supporting multiple simultaneous instances for the same Actor, even where a specific case is typically single-valued.
- The relationship's own business data (grades, condition, compensation, progress) is not part of the relationship record itself — it lives in the consuming domain, referencing the relationship.

If any of these six fail, do not use this pattern — Event Attendance fails the first; Reception's Host field fails the second and third. This list exists to make the ADR a decision tool, not only a description of one.

**4. Explicitly out of scope — failed the stress test, not silently omitted.** Guardian/Parent relationships (Actor-to-Actor, not Actor-to-Resource — a Person-to-Person relationship graph, a genuinely different pattern). Event Attendance (a point-in-time Transaction/Audit-log fact, not an ongoing relationship with effective dates — though Event *Registration*, kept distinct from raw attendance, may still qualify). Reception's Visit "host" field (a plain reference on a short, bounded Transaction — not every Actor-to-Resource connection needs this pattern's full machinery; some are correctly just a field).

**5. Still uncertain — resolved in principle by the Actor generalization, not yet empirically confirmed.** Visitor/Visit, Smart Campus's Access Credential. Both are plausible under the corrected definition but untested against a real consumer.

**6. Governing principle, elevated to platform-wide status.** *Shared patterns own structure; consuming domains own semantics and business rules.* Verified, not assumed, against five existing named patterns before being stated here: Approval Engine (workflow structure vs. domain-specific who/why), Registry Pattern (`ADR-0018` Decision 7 — catalog structure vs. Configuration/Provider Registry's own contents, the direct precedent this whole ADR follows), Document Engine (template/generation structure vs. domain-specific document meaning), Notification (delivery-channel structure vs. domain-specific trigger/message meaning), and by extension the still-unbuilt Workflow pattern. This governs every shared mechanism on this platform going forward, not only this one.

## Validation Plan

This proposal is validated empirically, not by further argument. The validator is the Learning Domain Blueprint — the pattern's first real consumer — when domain design work on Learning resumes.

**Validation succeeds only if all three hold:**
- Learning consumes the pattern without requiring any structural change to it — no new field, no altered semantics, no exception carved out for Learning's own needs.
- No new relationship metadata is introduced outside the pattern. If Learning's participation model needs a field this pattern doesn't already provide, that is evidence the pattern is incomplete — not license for Learning to extend it unilaterally.
- Learning-specific business data (Progress, Grades, Certificates) remain owned by Learning, referencing the relationship — never absorbed into the pattern itself.

**If all three hold**, this ADR is promoted from Proposed to Accepted.

**If any fails**, the pattern's shape is revisited before Accepted status is considered — the gap becomes new evidence, the same discipline that already produced two corrections (the Actor generalization, the Governed Resource renaming) before this version was written down.

**Outcome (2026-07-26):** all three conditions passed against Learning's Blueprint — Condition 1 (no structural change): Pass, Learning declared Course Offering/Course Template as Governed Resource types and its own Capacity vocabulary through the pattern's existing declaration mechanism, nothing added to the pattern itself. Condition 2 (no new relationship metadata): Pass, Learning's own Domain Invariant 5 and Architecture Validation section confirm Progress/Grade/Certificate-eligibility all reference the Participation record rather than extending it. Condition 3 (business data stays domain-owned): Pass, the same sections confirm Learning retained ownership throughout. Promoted to Accepted.

## Consequences

If Accepted, Employment, Enrollment, and Teacher/Homeroom/Coordinator Assignment become retroactively-recognized specializations of this pattern — **not renamed**, the same relationship the Registry Pattern already has to Configuration Registry and Provider Registry. Learning, once its Blueprint resumes, declares Course Offering and Course Template as Governed Resource types and its own Capacity vocabulary (Learner, Instructor, TA, Content Author, Reviewer, Guest Lecturer), rather than inventing its own relationship mechanism. Guardian/Parent and Event Attendance remain domains' own concerns, explicitly not pulled into this pattern despite superficial resemblance.

**Now Accepted:** the pattern is a platform guarantee. Employment, Enrollment, and Teacher/Homeroom/Coordinator Assignment are retroactively-recognized specializations as of this promotion — not renamed, per Decision 6 above.

## Alternatives Considered

- **Assignment evolves directly into the generic pattern** (all specializations renamed "Assignment"). Rejected — would destructively rename two already-frozen, independently-governed concepts (`ADR-0004`: Enrollment separate from Student; `ADR-0005`: Employment separate from Employee) for no functional gain.
- **No shared pattern; each domain reimplements independently.** Rejected — the exact anti-pattern Number Generator, Document Engine, and Approval Engine already exist to prevent.
- **A universal pattern covering every Person-to-X relationship on the platform.** Rejected by the stress test itself — Guardian/Parent and Event Attendance prove this does not generalize that far; documenting the actual boundary was the correct outcome, not forcing a universal claim to survive.

## References

`docs/adr/0018-configuration-platform-resolution-and-metadata.md` (Registry Pattern precedent, Decision 7; the historization column convention — effective dates, reason code, assigned-by/ended-by — cited in Decision 1 above). `docs/adr/0019-integration-platform-architecture.md` (Provider Registry, the second confirmed Registry Pattern instance). `docs/adr/0020-effective-dated-business-policy-pattern.md` (the shared-pattern/consuming-domain division already established there, the direct precedent for this ADR's governing principle). `docs/adr/0004-enrollment-separate-from-student.md`, `docs/adr/0005-employment-separate-from-employee.md` (the reason the "evolve Assignment directly" alternative was rejected). `docs/business-domains/reception.md` and `docs/adr/business/0022-reception-domain-boundary.md` (Visitor's deliberate non-Person design — the origin of the Actor generalization in Decision 1).
