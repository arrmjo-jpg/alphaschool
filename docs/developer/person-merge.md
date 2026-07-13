# Person Merge (Sprint 3.2)

**Status:** Complete, frozen as `v1.0-identity-maintenance-merge`. Governed by `docs/DOMAIN_BLUEPRINT.md` Addendum C4/C7-C10 and `docs/adr/0014-person-merge-architecture.md`.

## The state machine

`MergeRequest`'s status transitions are enforced by the model itself (`booted()`'s `saving()` guard consulting a `const TRANSITIONS` map), never left to a controller to set arbitrarily. Full diagram in the model's own docblock. Key points:

- `draft` self-loops on a failed dry run (retryable, not a separate `dry_run_failed` state) — `last_dry_run_result`/`last_dry_run_at` record the outcome without a status change.
- `failed` → `approved` is a valid, explicit retry after investigation — a fresh `execute()` call re-enters `executing` through the normal locking/guard path, no special resume bypass.
- `rollback_failed` has **zero outgoing transitions** — an emergency terminal state; recovery is an out-of-band manual procedure, never a modeled application action.
- Rollback runs its own full approval cycle (`rollback_requested` → `rollback_approved` → `rolling_back` → `rolled_back`), tracked via a separate `rollback_approval_request_id` column, distinct from the merge's own `approval_request_id` — not the same `pending_approval`/`approved` labels reused for two conceptually different approvals.

## Contract changes every implementer now carries

`ReassignsIdentityReferences::reassignPerson()` gained `bool $dryRun = false`; the interface gained `previewReassignment(): ReassignmentImpact[]`. Both were already foretold in the contract's own Sprint 2.1 docblock. Every implementer as of this sprint:

| Class | `reassignPerson()` shape | Why |
|---|---|---|
| `Person` | Real cascade to Contact/Address/PersonIdentityDocument + Media | Aggregate root; Media reassociation lives here entirely (see below) |
| `Employee`/`Student`/`Guardian`/`User` | Real, unique-constraint self-check on `dryRun` | Person's own context aggregates, `person_id` unique |
| `GuardianStudent` | Deliberate no-op | `guardian_id`/`student_id` reference Guardian/Student's own stable ids, not Person directly — already handled at that layer |
| `PersonRelationship`/`DuplicateFlag` | Real, with self-reference exclusion | Direct `person_id`-shaped columns; a row already linking the losing and winning Person would otherwise collapse into a self-reference, violating its own guard |
| `MergeRequest` | Deliberate no-op | Immutable historical record — see ADR-0014 |

## Merge Strategy — field-by-field resolution, delegated

`App\Modules\IdentityMaintenance\Support\MergeFieldResolver` (interface) + `WinningPersonAlwaysWinsFieldResolver` (default, only implementation this sprint): the winning Person's own fields always win, unconditionally — no gap-filling from the losing side. `MergeOrchestrationService` only decides *when* resolution happens (one step in `execute()`), never *how*. The losing Person's field values are preserved in `MergeRequest.losing_person_snapshot` (captured at execute time, not request time) for audit and rollback restoration.

## Media — People/Media boundary preserved

Identity Maintenance has zero knowledge that a `photo` collection, or any collection name, exists. Reassociation logic lives entirely inside `Person::reassignPerson()`: if the winning Person already has a photo, the losing Person's stays where it is (never deleted); otherwise it moves. Identity Maintenance just calls `Person::reassignPerson()`, exactly as it calls every other implementer.

## `DuplicateFlag` lifecycle

New terminal status `STATUS_MERGED`, set on the *originating* flag (if `duplicate_flag_id` was provided) once `execute()` commits. Every *other* flag referencing either merged Person cascades normally via the generic contract mechanism. The self-reference exclusion (`reassignPerson()` skips any row where the update would collapse `source_person_id`/`candidate_person_id` onto the same value) is what keeps the originating flag itself from crashing that same cascade — found as a real, reproducible bug while designing this feature, not a hypothetical.

## Execution and rollback locking

Both use the identical two-phase transaction pattern: a short first transaction locks the row (`lockForUpdate()`), re-verifies status, and flips it to `executing`/`rolling_back`; the real work runs in a second, separate transaction. A concurrent second caller's own lock+check sees the already-committed `executing`/`rolling_back` status and is refused — proven with a real dual-connection concurrency test (`tests/Feature/IdentityMaintenance/MergeExecutionLockingConcurrencyTest.php`), not assumed from the presence of `lockForUpdate()` in the code.

## Rollback safety

`RollbackSafetyChecker` walks every `MergeReassignmentLog` row for a `MergeRequest` and confirms the named entity's field still holds the value the merge set — if anything else has changed it since (a second merge stacked on top, an independent edit), rollback is blocked entirely, never partially reversed.

## Permissions

`identity.request-merge` (new this sprint, distinct from `identity.review-duplicates` now that `duplicate_flag_id` is optional) gates creating a `MergeRequest`, granted to `registrar`. `identity.approve-merge` (seeded Sprint 3.1) gates approve/reject/rollback, resolved to a role via `ApprovalRoutingResolver` — **exactly one role must hold it before Merge can function at all**, a hard prerequisite this sprint's own architecture review surfaced.

## API surface

A functional admin surface only (`/api/v1/merge-requests/*`), no UI polish. Every write action is `Gate::authorize()`-gated via `MergeRequestPolicy`; step-level approval eligibility stays `ApprovalEngine`'s own job, never re-implemented in the controller.

## What this sprint deliberately does not build

Anonymization (Sprint 3.3). Cross-module domain vetoes with real teeth (`VetoesPersonReassignment` exists, zero real implementers — Academic/HR/Finance don't exist yet to veto anything). Automatic merge suggestion beyond Sprint 3.1's on-demand duplicate scanning.
