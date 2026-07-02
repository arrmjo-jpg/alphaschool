# Approval Engine

`App\Core\Services\ApprovalEngine` (docs/DOMAIN_BLUEPRINT.md §6/§13) is a generic, sequential multi-step approval workflow. It is one of the few places in Core where a polymorphic reference (`requestable_type`/`requestable_id`) is the correct design — see below for why that's not a contradiction of the Assignment pattern's anti-polymorphism stance.

## Why polymorphism is right here but wrong for Assignment

The dividing line (docs/DOMAIN_BLUEPRINT.md, Shared Patterns): polymorphism is appropriate for genuinely shallow, generic coordination concerns that don't need domain-specific richness. Approval's entire job is "track who must approve what, in what order, and record the decision" — it never needs to know whether the thing being approved is a Merge request, a Leave Request, or a Scholarship grant. Assignment, by contrast, needs real FKs (a Section, a Route) for the *owning module's own reporting* — that richness is exactly what a generic polymorphic table would flatten and lose. Approval has no such need, so a shallow polymorphic reference costs nothing.

## Sequential, all-or-nothing

Steps are approved in order (`current_step_number` advances only when the current step is approved). Rejecting any single step rejects the entire request — this is not a majority-vote or partial-approval mechanism.

## Eligibility: by user, by role, or both

Each step declares `required_user_id` and/or `required_role`. Role checks are **duck-typed** (`method_exists($approver, 'hasRole')`) rather than importing Spatie Permission's classes directly — Core stays testable with plain models that have no roles at all, and this will integrate automatically once Identity (Phase 2) puts Spatie's `HasRoles` trait on `User`.

## No-self-approval is opt-out, not opt-in

`disallow_requester_as_approver` defaults to `true`. This matches the governance already decided for Identity Maintenance (Merge/Anonymization never allow self-approval, not even for Super Admin, since that's precisely the role with the most reach to cause damage acting alone) — the safer default applies everywhere unless a caller has a specific reason to opt out (e.g. a trivial single-role deployment where the distinction doesn't apply).

## Actor references are User IDs by convention, never a hard FK to `users`

`requested_by_id`, `required_user_id`, and `decided_by_id` all store a User ID — but deliberately as plain `unsignedBigInteger` columns, not `->constrained('users')`. This was a real mistake caught during an ADR compliance review after Sprint 1.2 first shipped: a hard foreign key from a Core migration into `users` (which becomes Identity's central Foundation table in Phase 2) is a literal violation of "Core depends on nothing else in the entire system — not even Foundation modules," regardless of how conceptually stable `users.id` is as a value. The columns still mean "a User ID" by convention — enforcing that referential integrity is the calling module's job, not Core's.

## Approval records are soft-deleted, never hard-deleted

Both `ApprovalRequest` and `ApprovalStep` use `SoftDeletes`. Approval decisions are evidentiary — who approved a Merge and when — and nothing in this engine currently calls `delete()`, but the schema itself must not allow that evidentiary trail to silently vanish if a future caller does. This is the same principle already established for Identity Maintenance ("the record of an erasure must itself never be erased"), applied here before Identity Maintenance (Phase 3) becomes this engine's first real high-stakes consumer.

## What this engine deliberately does not do

- It does not decide *who* should approve *what* — that's domain knowledge the calling module supplies when it calls `request()`.
- It is not the Workflow Engine. A configurable, admin-editable multi-step *process* (with branching, conditional steps) is a larger, separate concern (docs/DOMAIN_BLUEPRINT.md §6) deliberately deferred until Admissions gives it a first real consumer to validate the abstraction against. Approval is one *building block* a future Workflow Engine step can use, not a replacement for it.
