# BUS-0008: Inventory's Top-Level Classification Is Business Purpose, Not Return Status or Tracking Granularity

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Inventory

**Related ADRs:** BUS-0009 (Tracking Strategy, the concept this test explicitly excludes from classification)

## Context

Inventory's three-way split (Student, Custody, Consumable) needed a checkable rule for where a new item type belongs, per the requirement that new categories never be introduced casually. The test went through two revisions before landing correctly, and this ADR exists specifically to record the failed attempts, not only the final answer.

## Problem

What is the correct, stable, cross-school-invariant test for classifying an item into one of the three Inventory categories?

## Alternatives Considered

- **"Is it returned? → Custody, full stop."** First draft. Rejected: misclassifies a returned textbook as Custody, when it plainly belongs to Student Inventory.
- **"Is accountability individual-unit or pool-level? → that decides Custody vs. Student Inventory."** First correction. Rejected on further review: tracking granularity is a school-configurable enforcement policy (a school may choose to serialize every textbook), not a fixed property of the item — it correlated with the right answer for common cases (laptops usually serialized, textbooks usually pooled) without being the actual cause, which is exactly why the error wasn't caught immediately.
- **Business purpose — educational material vs. accountable property — as the sole classification input, with tracking granularity demoted entirely out of classification.** Accepted.

## Final Decision

Three-question test: (1) is it non-returnable and does it survive use → Student Inventory (Permanent Issue) or Consumable; (2) if returnable, is its purpose to let a student learn with it (educational material) → Student Inventory (Annual Reusable Issue), or is it equipment/property the institution holds someone accountable for → Custody Inventory, regardless of recipient. Tracking granularity (pool vs. serialized) is never part of this test — see BUS-0009.

## Why This Decision Was Chosen

Business purpose is stable across every school and every deployment; tracking granularity varies by school policy. A classification test has to run on the thing that doesn't change, or it silently breaks the first time a school makes a different enforcement choice than the one the test-writer had in mind.

## Consequences

Easier: the test survives contact with real school-to-school policy variation. Harder: it took two visible corrections to get here — a cost worth accepting openly rather than presenting the final version as if it were obvious from the start.

## Future Implications

Any future Inventory item type (a new one-off gift item, a new kind of tool) runs this same three-question test; it is never added as a fourth top-level category.

## Traceability

- **Business requirement:** "don't introduce a new top-level category unless there's a fundamentally different business lifecycle," stated as a governing instruction during Inventory's design.
- **Introduced in:** the Inventory Domain Revision turn (Student/Custody/Consumable split); corrected in the immediate follow-up turn (Permanent Issue vs. Annual Reusable Issue) and corrected again in the turn after that (business purpose vs. tracking granularity).
- **Depended on by:** BUS-0009.
