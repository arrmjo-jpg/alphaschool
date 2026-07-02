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

## What this engine deliberately does not do

- It does not decide *who* should approve *what* — that's domain knowledge the calling module supplies when it calls `request()`.
- It is not the Workflow Engine. A configurable, admin-editable multi-step *process* (with branching, conditional steps) is a larger, separate concern (docs/DOMAIN_BLUEPRINT.md §6) deliberately deferred until Admissions gives it a first real consumer to validate the abstraction against. Approval is one *building block* a future Workflow Engine step can use, not a replacement for it.
