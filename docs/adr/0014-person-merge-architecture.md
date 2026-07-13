# ADR-0014: Person Merge Architecture

**Status:** Accepted

**Date:** 2026-07-13

## Context

Sprint 3.2 built Person Merge (Addendum C4/C7-C10) — the highest-stakes operation in the system. Several genuine ambiguities the frozen Blueprint left open needed resolving before implementation could proceed, each with real, load-bearing consequences for how the system evolves once Academic/HR/Finance exist. Retroactively, this ADR also formalizes two Sprint 3.1 decisions (the `OwnedByAggregate` pattern and C11's "declare none" simplification) that were documented in developer notes but never given ADR weight, since Sprint 3.2 depends directly on both holding.

## Decisions

**1. `ApprovalEngine` (Core, Sprint 1.2) is never modified to understand permissions.** C10 requires approval routed through a permission (`identity.approve-merge`), but `ApprovalEngine`'s step model only understands roles/user IDs. Resolved via `App\Modules\IdentityMaintenance\Support\ApprovalRoutingResolver` — an interface referencing only plain strings, never a Spatie class, translating a permission name into `ApprovalEngine`'s existing step shape. Today's concrete policy (`SingleRoleApprovalRoutingResolver`) requires exactly one role hold a given permission, throwing clearly on zero or multiple — a current policy, not an architectural ceiling: the interface already returns an ordered list of steps, so a future sequential multi-approver chain needs no change to `ApprovalEngine` or to this interface, only a new resolver implementation.

**2. Structural-conflict validation lives in each contract implementer's own `dryRun` self-check, not hardcoded in Identity Maintenance.** C9 says structural conflicts are "validated directly by Identity Maintenance" — read here as *enforced* (Identity Maintenance calls every registered implementer's dry-run and blocks on any failure) rather than *hardcoded* (which would require a Foundation module to know Domain-shaped specifics like "`employees.person_id` is unique," cutting against the entire reason the contract pattern exists). `ReassignsIdentityReferences::reassignPerson()` gained a `$dryRun` parameter and a companion `previewReassignment(): ReassignmentImpact[]` method, exactly as that contract's own Sprint 2.1 docblock already foretold for "Phase 3, Sprint 3.2."

**3. `MergeRequest` and `MergeReassignmentLog` implement the Identity Maintenance contracts as deliberate no-ops.** Both hold `*_person_id`-shaped columns (flagged correctly by the Sprint 3.1 schema scanner) but are immutable historical records — "at this point in time, Person X merged into Person Y." A later, unrelated merge involving Person Y must never rewrite this record to track it forward; doing so would silently rewrite history, exactly what §7's "never overwrite history" principle exists to prevent. `MergeReassignmentLog` uses `OwnedByAggregate` against `MergeRequest` for the identical reason.

**4. `OwnedByAggregate` (retroactively formalized, introduced Sprint 3.1).** A model with a Person-shaped column but no independent registration in Identity Maintenance's Merge orchestration (e.g. `Contact`/`Address`/`PersonIdentityDocument`, cascaded by `Person::reassignPerson()`) declares `owningAggregate(): string` instead of implementing the two real contracts — a positive, auditable claim ("I am owned, by whom"), verified by the schema scanner checking the named aggregate itself holds up its end, not a blanket exemption.

**5. C11's "declare none" requirement is deliberately not implemented (retroactively formalized, decided Sprint 3.1).** The frozen Addendum C11 text asks every module to explicitly declare "I hold no Person reference," not just modules that do. Simplified per explicit instruction to reduce ceiling complexity: a model with no matching column asserts nothing at all. This trades away the "deliberate, auditable absence" guarantee C11's literal text describes, in favor of a simpler system — recorded here so a future reader of C11 isn't confused about why no such marker exists for the negative case.

**6. Rollback requires the same approval discipline as the merge itself**, including its own distinct `ApprovalRequest` cycle (`rollback_approval_request_id`, separate from `approval_request_id`) and the same no-self-approval guarantee. Not stated anywhere in the Blueprint; decided by the same four-eyes reasoning C10 already gives for the forward merge — reversing one is itself a significant identity-graph mutation.

**7. Execution and rollback both use a two-phase transaction design for locking**, not one long transaction: a short first transaction locks the row, verifies status, and flips it to `executing`/`rolling_back`; the real work runs in a separate transaction so that a failure can still persist `failed`/`rollback_failed` status (which would itself be rolled back if nested inside the failing transaction). `rollback_failed` is a deliberate emergency-terminal state with zero outgoing transitions — recovery is an out-of-band manual procedure, never a modeled application action.

## Consequences

Every future `ReassignsIdentityReferences` implementer (Enrollment, Employment, and beyond) inherits the `$dryRun`/`previewReassignment()` shape and the `OwnedByAggregate` exemption pattern with no further Core changes. `ApprovalEngine` remains genuinely generic — a second real consumer (Merge) has now proven its shape without requiring modification, the validation this project's own discipline has repeatedly sought before trusting an abstraction. The C11 simplification means a genuinely undeclared Person-reference column with no matching pattern would still pass silently — an accepted, documented trade-off, not an oversight.

## Alternatives Considered

- **Add `required_permission` directly to `ApprovalEngine`'s step definition.** Rejected — couples Core to Spatie/permission concepts it has no reason to know about, violating Core's domain-agnosticism the same way a standalone Erasure Engine in Core was rejected in ADR-0007.
- **Refactor `Person::reassignPerson()` into a thin orchestrator delegating to `Contact`/`Address`/`PersonIdentityDocument`'s own contract implementations**, making every Person-referencing table uniformly implement the real contracts with no exemption. Rejected — reopens Person's aggregate-root responsibility for no real benefit; `OwnedByAggregate` teaches the scanner to recognize ownership instead of reshaping the domain model around the scanner's own limitation.
- **One long transaction spanning lock-acquire through execution completion.** Rejected — a failure deep in the reassignment work would roll back the `failed` status update alongside everything else, leaving a `MergeRequest` stuck in `executing` with no record of what happened.

## References

`docs/DOMAIN_BLUEPRINT.md` Addendum C (C4, C7, C8, C9, C10, C11). ADR-0007, ADR-0009. `docs/developer/identity-maintenance-contract-governance.md` (Sprint 3.1), `docs/developer/person-merge.md` (Sprint 3.2).
