# Identity Maintenance: Contract Governance & Duplicate Resolution (Sprint 3.1)

**Status:** Complete, frozen as `v0.9-identity-maintenance-detection`. Governed by `docs/DOMAIN_BLUEPRINT.md` Addendum C (especially C11) and `docs/adr/0007`, `0009`.

## The schema scanner replaces a hand-maintained list

`tests/Architecture/IdentityMaintenanceSchemaDeclarationTest.php` discovers every Eloquent model under `app/Modules/*/Models` and `app/Core/Models`, checks each against its own real migrated schema (not a guess), and requires: any model whose table has a `person_id`/`*_person_id`/`student_id`/`employee_id`/`guardian_id`-shaped column must implement `ReassignsIdentityReferences` and `RedactsPersonalData` (Addendum C3) — unless it implements `App\Core\Contracts\OwnedByAggregate` instead. Supersedes Sprint 2.4's hand-listed version, whose own docblock said as much.

**Simplified per explicit instruction, deviating from C11's literal text:** a model with *no* matching column asserts nothing — no "declares none" marker interface. C11's literal wording wanted every module to explicitly declare absence too ("makes absence deliberate and auditable"); this was traded for simplicity. Recorded here so a future reader of C11 isn't confused about why no such marker exists for the "none" case.

## `OwnedByAggregate` — a positive claim, not a blanket exemption

Some models (`Contact`, `Address`, `PersonIdentityDocument`) have a direct `person_id` column but are owned children of `Person`, which already cascades to them (`Person::reassignPerson()`, Sprint 2.1, untouched). Forcing them to independently re-implement the same reassignment would duplicate logic Identity Maintenance never calls on them directly (it registers aggregate roots, not their private children).

`OwnedByAggregate` requires `owningAggregate(): string` — not a bare marker. The scanner verifies **both halves** of the claim: the model implements `OwnedByAggregate`, *and* the named aggregate actually exists and itself implements both real contracts. A model claiming ownership by a class that doesn't hold up its end is caught, not trusted — proven with a real negative test (temporarily pointed `Contact::owningAggregate()` at a non-compliant class, confirmed the check failed, restored it).

**Do not refactor Person into a thin orchestrator delegating to Contact/Address/PersonIdentityDocument's own contract methods.** This was proposed and explicitly rejected — Person, as aggregate root, keeps the orchestration; the scanner was taught to recognize ownership instead of the domain model being reshaped around the scanner's limitations.

**Deliberate scanner scope, not technical debt:** this scans Eloquent *model* classes, not raw tables. A pivot with a matching column but no dedicated model (`household_members`, `billing_group_members` — Sprint 2.5) is invisible to it by design. If either is ever promoted to a first-class model, it falls under this scanner's coverage automatically.

## Duplicate Resolution

`DuplicateDetectionService` (Core, Sprint 2.1) stays exactly as domain-agnostic as designed — a stateless scoring algorithm, no persistence, no decisions. `App\Modules\IdentityMaintenance\Services\DuplicateResolutionService` is the workflow layer: `scanForCandidates()` narrows by `Person.search_key` before scoring (never a full-table scan), `flagCandidates()` persists `DuplicateFlag` rows idempotently, `resolveAsMergeCandidate()`/`dismiss()` require `identity.review-duplicates`.

`DuplicateFlag.source_person_id`/`candidate_person_id` mirror `DuplicateDetectionService::score()`'s own probe/candidate vocabulary — chosen over a primary/duplicate label specifically because the latter would presuppose a resolution outcome before any review has happened.

**On-demand only this sprint** — no listener fires automatically on Person creation. That would require inventing a domain event not named anywhere in this sprint's scope; wiring it is a future decision, not an oversight.

**`status = 'merge_candidate'` is a triage classification only.** It does not create or link to a `MergeRequest` — that aggregate, and Merge execution itself, is Sprint 3.2's job.

## Identity Governance permissions

Seeded as its own Permission Group (Addendum C10: "not generic admin access"), separate from the existing `identity` group. `identity.review-duplicates` is enforced this sprint and granted to `registrar`. `identity.approve-merge`/`identity.approve-anonymization` are seeded as vocabulary only — which role(s) should hold that authority is a business decision for Sprint 3.2/3.3, not one this sprint made on the business's behalf.

## Two gotchas found this sprint, worth knowing before writing the next permission check

- **`$user->can(...)` silently fails in this app.** The default auth guard (`config/auth.php`) is `web`; every permission is seeded under `sanctum` (`PermissionSeeder::GUARD`). `can()` resolves against the default guard and finds nothing. Use `$user->hasPermissionTo($permission, 'sanctum')` explicitly — this sprint is the first real permission-check consumer beyond the Super Admin `Gate::before` bypass, so there was no established pattern to follow until now.
- **Global test helper collisions are real.** Two independently-written test files both declared `function withTeam(?int $branchId)` — a PHP fatal error the moment both load in the same run. Shared, reusable test helpers belong in `tests/Pest.php`'s Functions section, not redeclared per file.
