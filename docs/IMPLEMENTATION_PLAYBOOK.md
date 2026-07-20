# AlphaSchool ERP — Phase 2 Implementation Playbook

**Status:** Domain Blueprint (docs/DOMAIN_BLUEPRINT.md) is frozen and is law. This document does not redesign anything in it — it sequences and operationalizes it. Any deviation discovered during implementation requires an approved ADR before code changes proceed; it does not get silently absorbed into a sprint.

**One sequencing correction to the example order, explained up front:** `users.person_id` is a real, required foreign key (Blueprint §8) — User cannot be fully built before Person exists. Identity and People are therefore run as **one combined phase** (Phase 2), not two sequential ones. Similarly, Identity Maintenance is sequenced *before* Admissions, not after — its contracts must exist for every subsequent Domain module to implement from their first migration (Addendum C3), and its Merge/Anonymization tooling is safest built before real production data accumulates, not after. Neither of these is an architecture change — they're delivery-sequencing corrections, the same category of correction already made once during the Blueprint's own validation (Admissions/Academic Year ordering, Addendum A1).

---

## How to use this document

Phases 0–4 are specified to full sprint detail — they are actionable today. Phase 5 onward is specified at epic level only, deliberately: writing full sprint detail for modules that start 6–12 months from now, before any lesson from Phases 0–4 has been learned, would be the same premature-specification mistake the architecture itself was built to avoid (Blueprint §20/A9 sequencing principle, applied here to planning instead of code). Each later phase gets its own full sprint breakdown, using the exact template established here, in a dedicated planning pass when the phase before it is nearing done.

---

## Global Engineering Discipline

### Definition of Done (baseline — every sprint must satisfy this, plus its own specific items)

1. All planned deliverables merged via reviewed PR(s).
2. All tests green: unit, feature, architecture.
3. Architecture tests pass (module boundary, temporal-pattern shape, contract-declaration checks — see Phase 0).
4. Larastan passes at the current baseline level with zero new suppressed errors.
5. Pint clean.
6. Migrations are reversible (`down()` implemented and tested) or explicitly documented as irreversible with a stated reason.
7. No TODO/FIXME merged without a linked follow-up ticket.
8. `docs/DOMAIN_BLUEPRINT.md` is unchanged, OR a linked, approved ADR justifies the change.
9. API docs (Scramble) regenerated and spot-checked for any new/changed endpoint.
10. Seeders updated for any new lookup/reference data.
11. `CHANGELOG.md` entry added.
12. **ADR compliance review performed** — every new component explicitly checked against the Domain Blueprint, existing ADRs, and Core/Foundation layering rules, not inferred from tests/Pint/deptrac passing (see "Sprint completion policy" below for why this must be a distinct pass).
13. **No unresolved architecture-review finding** — any finding from item 12 is fixed (or explicitly, documentedly deferred) before the sprint is done.
14. **The sprint's Git tag points to the commit where items 1–13 are all true** — not an earlier commit later found to need a fix.

### Quality gates — nothing merges to `main` without all of these being true

- [ ] All automated tests green (unit, feature, architecture)
- [ ] Larastan — zero new errors
- [ ] `deptrac` — zero module-boundary violations
- [ ] Pint — clean
- [ ] No direct cross-module Eloquent access introduced (deptrac catches most of this; reviewer confirms the rest)
- [ ] Documentation updated per the table in "Documentation Discipline" below
- [ ] If the PR touches anything on the Blueprint's frozen list (Addendum A8) — a linked, approved ADR is attached, or the PR is rejected outright
- [ ] Reviewed by someone other than the author. On a solo-developer team, this becomes a mandatory 24-hour cooling-off self-review pass against this same checklist before merge — not skipped, just re-assigned to "future you."

### Sprint completion policy (standing rule, established after Sprint 1.2's ADR compliance review)

A Sprint's Git tag is a claim: "this state is architecture-approved and safe to build on." A Sprint is not complete, and its tag must not be created or moved onto a commit, until all five of the following hold:

1. **Tests pass** — the full suite (unit, feature, architecture), not just the sprint's own new tests.
2. **ADR compliance passes** — every new component checked against `docs/DOMAIN_BLUEPRINT.md`, the existing ADRs (`docs/adr/`), Core boundary rules (domain-agnosticism, promotion-not-prediction, low-churn), and Foundation/Domain layering. This is a distinct, deliberate pass, not assumed from tests passing — Sprint 1.2 shipped with a real Core→Foundation FK violation that every test suite, Pint run, and deptrac check missed, because none of them check *this specific thing*.
3. **Architecture review passes** — no unresolved finding from the ADR compliance pass. If a finding is found, it gets fixed (or explicitly deferred with a documented reason) before the sprint is considered done, not noted and left for later.
4. **Documentation is updated** — per the Documentation Discipline table below, including capturing what an ADR compliance pass found and fixed (see `docs/developer/approval-engine.md`'s "Actor references are User IDs by convention" section for the expected shape of this).
5. **The Git tag points to the approved commit** — if a compliance review finds something after a tag was already created, the fix lands in a new commit and the tag moves to it. A tag pointing at a pre-fix commit is not a historical curiosity to leave alone — it's a false claim that stays false until corrected.

This section itself is now part of the Definition of Done for every subsequent sprint in this document — items 2–3 above are additions to the baseline Definition of Done list, not a one-time reaction to Sprint 1.2 specifically.

---

## Implementation Order

```
Phase 0 — Engineering Bootstrap                 (tooling, CI, ADR backfill — before any domain code)
Phase 1 — Core Domain                           (temporal pattern, Number Generator, Approval Engine, Media skeleton)
Phase 2 — Identity & People Foundation          (combined — see sequencing note above)
Phase 3 — Identity Maintenance                  (Merge, Duplicate Resolution, Correction, Recovery, Anonymization)
Phase 4 — Admissions + Enrollment               (combined per Blueprint Addendum A1)
Phase 5 — Academic build-out                    (Sections, Timetables, Attendance, Grades, Teacher Assignments)
Phase 6 — HR                                    (Employment, Position, Salary, Assignment instances)
Phase 7 — Finance                               (Invoices, Journals, Fee Plans, Billing Policies)
Phase 8 — Inventory / Library / Transportation / LMS / Reporting   (parallelizable — see below)
Phase 9 — Maintenance / CRM                     (undesigned — needs its own architecture session first, like Family did)
```

Phases 0–4 are strictly sequential — each is a hard dependency of the next, and none of it can be parallelized across multiple teams without duplicating work or violating the identity substrate everything else depends on. Phase 5 onward opens up real parallelization — detailed in "Parallel Development Strategy" below.

---

## Phase 0 — Engineering Bootstrap

### Epic 0.1 — Repository & Tooling Setup

#### Sprint 0.1.1 — CI, static analysis, architecture testing, ADR backfill

**Goal:** every quality gate this playbook assumes is wired up and *proven to actually catch violations* before a single domain model exists.

**Scope — IN:** Pint config; Larastan installed with a baseline (start at level 6, ratchet upward later — recorded as a technical-debt item); `deptrac` installed with layer config matching Blueprint §2 (Foundation vs. Domain, no Domain-to-Domain edges); Pest installed with an architecture-test suite skeleton; CI pipeline running Pint + Larastan + deptrac + Pest (unit/feature/arch) on every PR; branch protection on `main` (CI must pass, one review required); PR template encoding the Quality Gates checklist above; `CHANGELOG.md` initialized; `docs/adr/` created with a standard template (Context / Decision / Consequences / Alternatives Considered) and ADRs 0001–000N backfilled for every major frozen Blueprint decision (Person-as-substrate, Applicant≠Student, Family-not-an-aggregate, Enrollment≠Student, Employment≠Employee, dedicated-instance commercial model, Identity Maintenance).

**Scope — OUT (build later):** no domain models beyond Laravel defaults; no API endpoints; no UI; no mutation testing yet (see CI/CD Timeline); no load/performance testing yet.

**Dependencies:** none — this is genuinely first.

**Deliverables:** CI workflow file(s); `phpstan.neon` + baseline; `deptrac.yaml`; `pint.json`; `.github/pull_request_template.md`; `docs/adr/template.md` + backfilled ADRs; `CHANGELOG.md`; `docs/developer/getting-started.md` (how to run tests/analysis locally).

**Definition of Done:** CI is green on a trivial commit. `deptrac` is proven to actually catch a violation — introduce a deliberate cross-namespace import, confirm CI fails, then remove it. Larastan runs clean at the chosen baseline. Every major frozen Blueprint decision has a corresponding ADR, linked from the Blueprint.

**Testing checklist:** CI dry run; deptrac negative-test (as above); Larastan baseline reviewed line-by-line to confirm nothing important was silently suppressed.

**Risks:** the single most common mistake here is deferring this sprint until "there's real code to analyze." That's backwards — retrofitting static analysis and architecture tests onto an existing, imperfect codebase produces a wall of pre-existing violations that gets baselined away wholesale, defeating the entire purpose. This sprint is non-negotiable and comes first.

**Git Milestone:** `v0.0-bootstrap`

---

## Phase 1 — Core Domain

### Epic 1.1 — Temporal & Assignment Pattern Foundation

#### Sprint 1.1.1 — `HasTemporalAssignment` trait + value objects + architecture tests

**Goal:** the shared temporal pattern (Blueprint §6, Addendum A3/B1) exists, is rigorously unit-tested, and is backed by architecture tests that enforce module boundaries from commit one.

**Scope — IN:** `HasTemporalAssignment` trait (open/close/replace, `asOf(date)` queries, overlap validation, `scheduled/active/ended/cancelled` status); `DateRange` value object (overlap + ordering validation, centralized); `ReasonCode` value object + lookup-table pattern; Pest architecture tests enforcing (a) Core imports nothing from Foundation/Domain namespaces, (b) Domain-tier namespaces never import sibling Domain-tier namespaces.

**Scope — OUT (build later):** no real business Assignment tables yet (Homeroom Teacher, Bus Driver — those arrive with Academic/HR in Phases 5–6); no Approval Engine yet (next sprint); no Workflow Engine at all yet — per Blueprint B6, it gets built against Admissions as its first real consumer in Phase 4, not speculatively now.

**Dependencies:** Phase 0 complete.

**Deliverables:** `app/Core/Concerns/HasTemporalAssignment.php`; `app/Core/ValueObjects/DateRange.php`; `app/Core/ValueObjects/ReasonCode.php`; `reason_codes` table + seeder scaffold; `tests/Architecture/CoreBoundaryTest.php`; `tests/Architecture/ModuleBoundaryTest.php`; `docs/developer/temporal-pattern.md`.

**Definition of Done:** trait unit-tested against real edge cases (adjacent-but-non-overlapping ranges, open-ended current ranges, attempted double-active-assignment); architecture tests demonstrably fail a deliberate violation, then pass once it's removed; developer guide published showing how a future module adopts the trait.

**Testing checklist:** trait unit tests (edge-case heavy — this logic is load-bearing for every future temporal table); architecture tests. No feature tests yet — nothing user-facing exists.

**Risks:** the temptation here is to over-generalize the trait for imagined future needs. Apply B1's rule directly: build exactly what Employment, Enrollment, and `guardian_student` already need (all three are fully specified in the Blueprint) — resist adding parameters for anything not already a named, specified consumer.

**Git Milestone:** `v0.1-core-temporal`

#### Sprint 1.1.2 — Number Generator + Approval Engine + Money

**Goal:** centralized, concurrency-safe number generation and a working generic Approval Engine, ready for later phases to consume — built now because both are genuinely domain-agnostic (Blueprint B1's Core test) and multiple later phases need them simultaneously.

**Scope — IN:** `number_sequences` table + `NumberGeneratorService` (atomic increment, format pattern, gapless-transactional mode vs. lenient mode per Blueprint §6); `ApprovalRequest`/`ApprovalStep` polymorphic aggregate + `ApprovalEngine` service; `Money` value object (currency-aware arithmetic, defined rounding behavior).

**Scope — OUT:** no real consumers wired up yet — Admissions, Finance, and Identity Maintenance will call these starting in Phases 3–4 and 7. No multi-currency ledger mechanics (see Technical Debt Register).

**Dependencies:** Sprint 1.1.1.

**Deliverables:** `NumberGeneratorService` + migration/model; `ApprovalRequest`/`ApprovalStep` migrations + models + `ApprovalEngine` service; `Money` value object.

**Definition of Done:** a concurrency test proves the Number Generator produces no duplicate or skipped values under simulated parallel requests for the same sequence; the Approval Engine can create a request, route through 2+ steps, and reach a final decision, fully unit tested; `Money`'s rounding behavior is documented and tested.

**Testing checklist:** **concurrency/race-condition test for the Number Generator is non-negotiable** — this is exactly where a naive `SELECT MAX(value)+1` implementation looks correct under single-developer testing and silently fails in production under real concurrent load; Approval Engine state-transition unit tests; Money arithmetic edge cases (rounding, currency-mismatch rejection).

**Risks:** skipping the concurrency test above is the single most common way this specific piece of infrastructure ships broken and isn't discovered until two invoices share a number in production.

**Git Milestone:** `v0.2-core-engines`

### Epic 1.2 — Media Architecture Skeleton

#### Sprint 1.2.1 — Disk tiers, path generator, private-file access control

**Goal:** the 3-tier disk/collection/path architecture (Blueprint §12) is provably correct before any real feature uploads a real file.

**Scope — IN:** `public`/`private`/`temporary` disk config (local driver for dev; S3-compatible driver pre-configured for R2, even without live prod credentials yet); custom `PathGenerator` implementing the `{tier}/{branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}` scheme; extended `Media` model (Spatie base + `LogsActivity` + soft-delete + `sensitivity` column per Addendum B3); authenticated private-file streaming route + base Policy; scheduled `temporary`-tier purge command.

**Scope — OUT:** no per-collection conversion profiles yet (those arrive with the first module that actually uploads photos — People, Phase 2); no OCR/AI hooks; no digital-signature tooling; no Document Governance parameter UI (retention/versioning configuration stays code-defined for now — see Technical Debt Register).

**Dependencies:** Phase 0.

**Deliverables:** custom path-generator binding; extended `Media` model + migration; `private-files` route + Policy; `PurgeTemporaryMedia` scheduled command (with dry-run mode).

**Definition of Done:** a file uploaded through each of the 3 tiers produces the correct physical path; a feature test proves a `private`-tier file returns 404/403 unauthenticated and 200 authenticated; the purge command is tested including its dry-run mode.

**Testing checklist:** the private-file access-control feature test is the single most important test in this sprint — an accidentally-public sensitive file is a severe real-world failure mode, and this must be proven with an actual unauthenticated HTTP request in a test, never inferred from config alone.

**Risks:** confusing "the collection is configured for the private disk" with "the file is actually inaccessible" — always verify the second, never assume it from the first.

**Git Milestone:** `v0.3-core-media`

---

## Phase 2 — Identity & People Foundation

*(Combined phase — see the sequencing note at the top of this document.)*

#### Sprint 2.1 — Person, identity documents, contacts, addresses, duplicate-detection

**Goal:** the identity substrate (Blueprint §8) exists as its own aggregate, fully independent of User.

**Scope — IN:** `Person` model (bilingual name parts, DOB, gender, nationality, photo collection); `person_identity_documents` (document_type + issuing_country + number, historized per Addendum A4/session on identity versioning); `contacts` and `addresses` child tables; the fuzzy duplicate-matching Core service (normalized `search_key` column + candidate scoring, per Blueprint §2) — the *algorithm* only, not yet wired into any registration workflow (that's Phase 3/4).

**Scope — OUT:** no `User` yet (next sprint — needs Person to exist first); no Employee/Student/Guardian context aggregates yet (Sprint 2.4); no Identity Maintenance contracts implemented yet (Phase 3, though the interfaces themselves get *defined* in this sprint so Person can implement them trivially).

**Dependencies:** Phase 1 (Money/DateRange/temporal trait not directly used by Person itself, but the `person_identity_documents` historization reuses the same conventions).

**Deliverables:** `Person` model + migration; `person_identity_documents` + migration; `contacts`/`addresses` + migrations; `DuplicateDetectionService` (Core); `PersonName` value object; `ReassignsIdentityReferences`/`RedactsPersonalData` interface definitions (Core, implemented trivially by Person in this sprint, by every future Person-referencing module thereafter); Media collection + conversion profile for Person's `photo`.

**Definition of Done:** Person can be created with full bilingual identity data; identity-document uniqueness is scoped to `(document_type, issuing_country, number)`, tested; the duplicate-detection service returns ranked candidates for a known fuzzy-match scenario (tested with real AR/EN transliteration pairs, e.g. "Mohammed"/"Muhammad"/"محمد").

**Testing checklist:** feature tests for Person CRUD; unit tests for identity-document uniqueness scoping; unit tests for duplicate-detection scoring against deliberately constructed near-miss and true-twin cases (twins must never score as a hard duplicate).

**Risks:** treating `search_key` as an afterthought — it must be computed and indexed from day one, since retrofitting it once real Person rows exist means a backfill migration under time pressure later.

**Git Milestone:** `v0.4-people-person`

#### Sprint 2.2 — User, Sanctum authentication, account-type derivation

**Goal:** working authentication, with User correctly modeled as auth-only per Blueprint §8.

**Scope — IN:** `User` model (`person_id` one-way FK, username/email/phone/password/status/last_login_at); Sanctum setup (API tokens for both the React admin and Next.js portal); account-type derivation logic (a computed property/service reading which context rows exist for a Person — not a stored enum); Super Admin `Gate::before` bypass.

**Scope — OUT:** MFA/2FA (flagged open in the Blueprint §16 — deliberately deferred, see Technical Debt Register); SSO/OAuth; impersonation ("login as," Blueprint §16 open item — deferred); step-up authentication UI (the *mechanism* — OTP to a verified contact — is stubbed as a service interface now, but the full guardian-registration flow it protects doesn't exist until Phase 4).

**Dependencies:** Sprint 2.1 (Person must exist for the FK).

**Definition of Done:** login/logout works for both consuming apps via Sanctum; a User with no context rows derives no account type and reaches no portal; Super Admin bypass is proven to cover a *newly created* branch with zero additional configuration (the exact guarantee it exists for).

**Deliverables:** `User` model + migration; Sanctum config for both SPA/token consumers; `AccountTypeResolver` service; `Gate::before` Super Admin bypass; `StepUpAuthentication` service interface (implementation stubbed, real OTP delivery wired once Notifications exists later this phase).

**Testing checklist:** feature tests for login/logout on both token types; unit test proving Super Admin bypass works against a branch created *after* the bypass logic was written (regression-proofing the exact failure mode it was designed to prevent).

**Risks:** implementing Super Admin as a role-per-team grant instead of a true bypass is the single most tempting shortcut here, and it's exactly the mistake Addendum/Blueprint §8 exists to prevent — test for it explicitly, don't just trust the code review.

**Git Milestone:** `v0.5-identity-auth`

#### Sprint 2.3 — Roles, Permissions, Permission Groups, Teams, Branches

**Goal:** the full authorization model (Blueprint §8) is live.

**Scope — IN:** Spatie Permission installed with Teams enabled (`team_foreign_key = branch_id`) from the start (never retrofitted, per the original session's explicit warning); `branches` table (`parent_branch_id`, `is_active`); `organizations`/`schools` minimal tables (Addendum A2/B — licensing metadata, one row); extended `Role`/`Permission` models with `permission_groups` (translatable) and `permission_group_id` FK; seeder-driven permission definitions (never admin-UI-creatable, per governance rule).

**Scope — OUT:** no role-assignment authority UI yet (who may assign which role in which branch — flagged as needing enforcement before go-live, Blueprint §16, deferred to closer to Phase 4/5 when real registrar workflows exist to test it against); no nested Permission Groups (not required yet).

**Dependencies:** Sprint 2.2 (Roles attach to User via Spatie's model-has-roles).

**Deliverables:** Spatie config with Teams enabled; `branches`, `organizations`, `schools` migrations + models; extended `Role`/`Permission` models + `permission_groups` migration; seeded baseline roles/permissions (Principal, Teacher, Registrar, HR Manager, Accountant, etc., per the original Users-module session's examples).

**Definition of Done:** a role assigned in Branch A does not grant access in Branch B; Permission Groups render correctly in both AR and EN; direct permission-to-user grants are technically possible in Spatie but confirmed *not exposed* anywhere in this codebase (an architecture/feature test, not just a UI omission).

**Testing checklist:** feature test for branch-scoped role isolation (assign in A, assert no access in B); test that no code path exists for direct permission-to-user assignment.

**Risks:** enabling Teams *after* real role data exists is the exact expensive mistake this sprint exists to avoid — there is no excuse for deferring this once this sprint starts.

**Git Milestone:** `v0.6-identity-authorization`

#### Sprint 2.4 — Employee, Student, Guardian context shells

**Goal:** the three context aggregates exist, referencing Person, with their coarse lifecycle statuses — deliberately *without* Enrollment or Employment yet (those are Phase 4 and Phase 6 respectively).

**Scope — IN:** `Employee`, `Student`, `Guardian` models (`person_id` FK, coarse `lifecycle_status`); `employee_branches` pivot shell (`started_at`/`ended_at`, per Addendum B2 — note this now belongs conceptually to the future Employment entity, but the physical table can exist now since Employment's full build is Phase 6; document this explicitly as a placeholder); each aggregate implements the Identity Maintenance contracts (trivial reassignment logic, since at this point there's nothing yet to reassign *to* — full teeth arrive once Enrollment/Employment exist).

**Scope — OUT:** no Enrollment (Phase 4); no Employment/Position/Salary history (Phase 6); no Student/Employee numbering yet (the Blueprint's own open question — global vs. branch-prefixed — must be answered before this sprint's numbering logic is written, see "Open Decision to Resolve" below).

**Dependencies:** Sprints 2.1–2.3.

**Deliverables:** `Employee`/`Student`/`Guardian` models + migrations; contract implementations (currently near-empty, but declared, satisfying Addendum C11's "mandatory declaration" rule from day one).

**Definition of Done:** a Person can simultaneously hold Employee and Guardian contexts (the exact scenario the whole Person-substrate decision exists to support) — this must be an actual passing test, not just theoretically possible.

**Testing checklist:** feature test for the multi-context-per-Person scenario; architecture test confirming all three models declare their Identity Maintenance contract status (implemented or explicitly "none").

**Open decision to resolve before this sprint starts:** Blueprint §16 leaves student/employee numbering scope (global vs. branch-prefixed) open. This sprint cannot proceed without an answer — flag to product/CTO decision-makers now, not mid-sprint.

**Risks:** silently reintroducing a single `account_type` enum "just to make the UI simpler" is the most likely regression here — it directly contradicts the entire reason Person exists.

**Git Milestone:** `v0.7-people-contexts`

#### Sprint 2.5 — Family relationships

**Status: COMPLETE, frozen as `v0.8-people-family` (2026-07-12).** All four steps (relationship_types, guardian_student, person_relationships, Household/BillingGroup) implemented, reviewed, and approved individually; `Household`/`BillingGroup` resolved as two independent shells rather than one, a genuine ambiguity the "Scope — IN" line below left open, not a deviation. No further Family-module work unless a real implementation bug, a security issue, or a new approved ADR requires it.

**Goal:** the Family architecture (Blueprint §11) is live: the safety-critical join and the informational graph, correctly separated.

**Scope — IN:** `guardian_student` (relationship_type, `is_primary_contact`, `is_pickup_authorized`, `custody_restriction_notes`, `verified_by`/`verified_at`, effective dates — using `HasTemporalAssignment`); `person_relationships` generic graph; `relationship_type` as a translatable lookup table (not an enum, per the session correction); `households`/`billing_groups` shell (administrator-curated, no Finance consumer yet).

**Scope — OUT:** no Finance consumption of Billing Groups yet (Phase 7); no guardian-verification/step-up-auth UI yet (Phase 4, alongside Admissions, where it's actually exercised); no Family-tree UI (a derived read — can be built any time after this sprint, not gated on anything further).

**Dependencies:** Sprint 2.4.

**Deliverables:** `guardian_student`, `person_relationships`, `relationship_types` (translatable), `households` + `household_members` migrations + models.

**Definition of Done:** a `guardian_student` relationship correctly rejects an overlapping active period for the same guardian-student pair (via `HasTemporalAssignment`'s overlap validation); Arabic paternal/maternal kinship terms (عم/خال, جد لأب/جد لأم) render as genuinely distinct `relationship_type` rows, not labels on one shared enum case.

**Testing checklist:** overlap-validation feature test on `guardian_student`; translation test for the Arabic kinship distinctions specifically (this is the concrete case that justified the lookup-table decision — it must be provably correct, not just theoretically supported).

**Risks:** collapsing `person_relationships` and `guardian_student` into one table "since they're similar" is the exact God-Object mistake the Family session spent an entire round avoiding — do not merge them under schedule pressure.

**Git Milestone:** `v0.8-people-family`

**Phase 2 production-readiness checklist:** see "End-of-Phase Checklists" below.

---

## Phase 3 — Identity Maintenance

#### Sprint 3.1 — Contract governance + Duplicate Resolution

**Status: COMPLETE, frozen as `v0.9-identity-maintenance-detection` (2026-07-13).** All scope items delivered and reviewed; two real gaps in already-frozen code (Sprint 2.4/2.5's `GuardianStudent`/`PersonRelationship`, and Sprint 2.2's `User`) were found by the new scanner and fixed as architectural compliance corrections, not reopenings of those sprints. See `docs/developer/identity-maintenance-contract-governance.md`.

**Goal:** the module-contract discipline (Addendum C11) is enforced by CI, and the Duplicate Resolution workflow (distinct from the Detection algorithm built in Phase 2) is live.

**Scope — IN:** architecture test scanning every module's schema for columns plausibly referencing Person (`*_person_id`, `student_id`, `employee_id`, `guardian_id`) and failing CI if a module hasn't declared its contract status; `DuplicateFlag` workflow (review a flagged candidate pair, resolve as merge-candidate or dismiss); Identity Governance Permission Group.

**Scope — OUT:** Merge execution itself (next sprint); Anonymization (Sprint 3.3).

**Dependencies:** Phase 2 complete (needs Person, Employee/Student/Guardian, and their contract declarations to scan).

**Deliverables (actual):** `tests/Architecture/IdentityMaintenanceSchemaDeclarationTest.php` (named differently than originally planned — `IdentityContractDeclarationTest.php` was Sprint 2.4's own file, this sprint's replaces it under a name reflecting what it actually does); `App\Core\Contracts\OwnedByAggregate` (not originally planned — added when the scanner surfaced that Person's owned children (`Contact`/`Address`/`PersonIdentityDocument`) need a way to declare "my aggregate root handles this" without duplicating its logic); `DuplicateFlag` model + `DuplicateResolutionService`; Identity Governance permission group + seeded permissions (`identity.review-duplicates` enforced and granted to `registrar`; `identity.approve-merge`/`identity.approve-anonymization` seeded as vocabulary only).

**Definition of Done:** the architecture test genuinely fails when a deliberately-added column is left undeclared, proving the safety net works before it's ever relied on for real. **Met, twice over** — proven for the base contract-presence check (temporarily stripped `User`'s declaration) and for the `OwnedByAggregate` ownership-claim check (temporarily pointed `Contact::owningAggregate()` at a non-compliant class).

**Testing checklist:** the contract-declaration architecture test's own negative case (prove it catches an undeclared reference) is the critical test here.

**Two gotchas found and documented, not just fixed silently:** `$user->can(...)` silently fails in this app (default guard `web`, permissions seeded under `sanctum` — use `hasPermissionTo($permission, 'sanctum')`); a global test-helper name collision (`withTeam()`, independently declared in two files) is now a shared helper in `tests/Pest.php`.

**Git Milestone:** `v0.9-identity-maintenance-detection`

#### Sprint 3.2 — Merge: Preview, Dry Run, Execute, Rollback

**Status: COMPLETE, frozen as `v1.0-identity-maintenance-merge` (2026-07-13).** See `docs/adr/0014-person-merge-architecture.md` and `docs/developer/person-merge.md` for the full architecture, including several refinements settled during design review before implementation began: `ApprovalEngine` stays generic (a new `ApprovalRoutingResolver` adapter, not a Core change); `MergeRequest.duplicate_flag_id` is nullable (manual/API/import merges supported, not only flag-originated ones); a dedicated `MergeFieldResolver` abstraction for field-by-field conflict resolution; the full state machine (15 states, every transition explicit and enforced by the model itself); rollback requires the same approval discipline as the merge.

**Goal:** the highest-stakes operation in the system, built exactly to the spec validated in Addendum C7–C9.

**Scope — IN:** `MergeRequest` aggregate + `merge_reassignment_log` child; `previewReassignment`/`reassignPerson` contract methods with a `$dryRun` parameter (not a wrapped-and-rolled-back transaction — a real parameter every implementing module respects); `canReassignPerson` validation contract (structural conflicts owned by Identity Maintenance directly; domain vetoes delegated to owning modules — though at this point in the timeline, only People's own structural checks have real implementations, since Academic/HR/Finance don't exist yet to veto anything); mandatory Approval-Engine gating with no self-approval, even for Super Admin; rollback using the reassignment log.

**Scope — OUT:** cross-module domain vetoes with real teeth (Finance's "reconciliation incomplete" check literally cannot exist until Finance exists in Phase 7 — the contract point exists now, specific module implementations arrive as each module is built); Merge UI polish beyond a functional admin screen.

**Dependencies:** Sprint 3.1.

**Deliverables:** `MergeRequest`/`merge_reassignment_log` migrations + models; contract method implementations with dry-run support; Approval-Engine integration (no self-approval, enforced test); rollback service using the reassignment log; rollback safety-check (detect post-merge dependent activity and block/warn).

**Definition of Done:** a full merge — preview, dry run, approval, execution, and reversal — is provable end-to-end in a feature test against the (currently limited, People-only) set of Person references that exist at this point in the build; the no-self-approval rule is proven even for a Super Admin account.

**Testing checklist:** end-to-end merge lifecycle feature test; concurrency consideration — what happens if two merge requests target the same Person simultaneously (should be prevented, test it); rollback-safety test (create dependent activity after a merge, confirm rollback is blocked or clearly flagged).

**Risks:** because Academic/HR/Finance don't exist yet, it's tempting to under-build the orchestration ("we'll add real cross-module reassignment later"). Don't — build the *mechanism* generically and correctly now; each later phase's module only needs to implement the interface, not redesign the orchestration.

**Git Milestone:** `v1.0-identity-maintenance-merge`

#### Sprint 3.3 — Identity Correction tiering, Recovery, Anonymization

**Goal:** the remaining three capabilities, correctly differentiated per Addendum C10.

**Scope — IN:** Correction tiering (cosmetic = immediate + reason + Activitylog; substantive fields like DOB/nationality = Approval-gated, same path as Merge); Recovery for Merge/Correction (using the reversibility already built in 3.2); `AnonymizationRequest` aggregate with its own Approval gate (no recovery path post-execution, by design); `sensitivity`-aware redaction respecting Media's classification (Addendum B3) for attached documents.

**Scope — OUT:** Activitylog redaction is flagged, not fully solved — this is the "genuinely gnarly technical wrinkle" named in the original erasure discussion; recommend a documented, explicit decision here (either redact matching JSON payload values across historical Activitylog entries, accepting the performance cost, or accept Activitylog as an intentional, documented exception with legal sign-off) rather than silently deferring it without a decision. **This specific point should become its own ADR before this sprint closes.**

**Dependencies:** Sprint 3.2.

**Deliverables:** Correction-tiering policy layer; `AnonymizationRequest` migration + model + workflow; Activitylog-redaction ADR + whichever implementation it resolves to.

**Definition of Done:** a cosmetic correction requires no approval and is provably distinct in the audit trail from a substantive one that does; an executed anonymization redacts the classified fields and is provably non-reversible through any code path; the Activitylog-redaction ADR is written, reviewed, and either implemented or explicitly deferred with a stated legal/product reason.

**Testing checklist:** tiering-boundary test (prove the exact field list that triggers approval, not just a vague "some fields"); anonymization irreversibility test.

**Git Milestone:** `v1.1-identity-maintenance-complete`

**Phase 3 production-readiness checklist:** see below.

---

## Infrastructure Track: Docker Development Environment

**Status: COMPLETE (2026-07-20).** See `docs/developer/docker-development.md`. Not a numbered backend Phase and not gated by one — an infrastructure milestone that paused new feature work by explicit instruction, replacing the Laragon-based local-install workflow entirely rather than adding it as a parallel option.

Nine services (`nginx`, `app`, `queue`, `scheduler`, `vite`, `mysql`/MariaDB, `redis`, `meilisearch`, `mailpit`) on one Docker network, addressed by service name — `DB_HOST=mysql`, `REDIS_HOST=redis`, `MEILISEARCH_HOST=http://meilisearch:7700`, `MAIL_HOST=mailpit`, never `127.0.0.1`/`localhost`. `backend/.env.example` itself was made Docker-native (so a fresh `cp .env.example .env` needs no manual editing) and switched `CACHE_STORE`/`SESSION_DRIVER`/`QUEUE_CONNECTION` from `database` to `redis`, per an explicit decision to exercise the Redis container rather than leave it idle. Database engine (MariaDB 11.4, matching the driver key already committed in `.env.example` over the MySQL 8.4 Laragon had actually been running) and PHP version (8.4, matching the developer machine's existing install over `composer.json`'s `^8.3` floor) were both open questions with no documented prior decision, resolved via explicit confirmation rather than silently picked.

`backend/vendor` and the npm workspace's `node_modules` (both root-level and `admin/`) are named Docker volumes, never bind-mounted from the Windows host — bind-mounting either through Docker Desktop's WSL2 layer onto NTFS is a well-known severe I/O penalty. `docker/nginx/default.conf` uses Docker's embedded DNS resolver (`127.0.0.11`) with a variable-based `fastcgi_pass` rather than a static one, specifically so Nginx re-resolves the `app` container's IP on every request instead of caching it once at startup — found as a real bug during verification (rebuilding/recreating `app` left Nginx 502ing against a stale IP until manually restarted).

Real bugs found and fixed during a live build-and-verify pass, not assumed working from the Dockerfile alone: (1) `ext-ffi` (needed by `jcupitt/vips`, `spatie/image`'s Vips driver, which talks to `libvips` via FFI rather than a compiled extension) was only half-configured — an `ffi.enable=1` ini directive was written, but the extension itself was never actually compiled via `docker-php-ext-install`, and its build additionally needed `libffi-dev`/`pkg-config`, neither originally installed; (2) `ext-exif` (needed by `spatie/image`/`spatie/laravel-medialibrary`) was missing entirely; (3) the root `package-lock.json`, originally generated by `npm install` running natively on Windows, pinned Vite's `rolldown` dependency to a Windows-platform native binding — a well-documented npm bug (npm/cli#4828) around optional dependencies and cross-platform lockfiles — causing the `vite` container to crash-loop with `Cannot find native binding`; fixed by regenerating the lockfile from inside the Linux container, the correct permanent fix now that Docker is the only sanctioned way to run this project; (4) `queue`/`scheduler`/`vite` all crash-loop on a truly clean `docker compose up -d --build`, since their named volumes start empty and each service's command (`php artisan queue:work`/`schedule:work`/`npm run dev`) runs immediately rather than waiting — `docker compose exec` can't reach a container that already exited, so the bootstrap sequence in `docs/developer/docker-development.md` installs dependencies first via `composer install`/`docker compose run --rm vite npm install`, then explicitly brings those three back up.

One pre-existing, out-of-scope bug was found and deliberately not fixed here, flagged as a separate task instead: `activity_log`'s polymorphic `subject_id` column (from Spatie's stock `nullableMorphs()` migration) is an `unsignedBigInteger`, incompatible with `Media`'s non-incrementing ULID string primary key (`getKeyType() === 'string'`) — any activity-logged Media model hits `SQLSTATE[01000]: Data truncated for column 'subject_id'` under strict SQL mode. This is pre-existing and unrelated to Docker; it was invisible against the test suite's default in-memory SQLite connection (no real column-type enforcement) and apparently against whatever `sql_mode` the prior Laragon MySQL install used, and only surfaced now that `php artisan test` was run inside the `app` container against a real, strict-mode MariaDB connection — 339/351 tests pass, the 12 failures are exactly this one schema mismatch, not a Docker regression. Verified: `docker compose exec app composer install`, `migrate` (44 migrations, clean), `php artisan test` (339/351, gap explained above and tracked separately), a live browser check of the admin SPA served from the `vite` container successfully making a real cross-origin request to the Nginx-fronted API (`GET http://localhost:8000/api/v1/workspaces → 401`, zero CORS errors, zero console errors), and Nginx surviving an `app` container restart without needing a restart itself (proving the DNS-resolution fix).

---

## Frontend Track F1 — Admin Platform Foundation

**Status: COMPLETE, frozen as `v1.0-admin-platform-foundation` (2026-07-13).** See `docs/adr/0015-admin-platform-foundation-frontend-architecture.md` (including two implementation-note amendments: React 19 in place of the originally-specified React 18, and the dev harness proving its frameworks against fixture data rather than "existing Identity endpoints" since no such list endpoints actually exist on the backend yet) and `docs/developer/admin-platform-frontend.md`. Not a numbered backend Phase — a parallel frontend track, run alongside Sprint 3.3 onward.

**Design System — FROZEN 2026-07-16** (`docs/ADMIN_DESIGN_SYSTEM.md`). A dedicated reverse-engineering and design pass, separate from and downstream of the architecture above: extracts the legacy admin's (`alqla-main/admin-frontend`) visual identity, UX language, and component behavior as journeys (not screens), inventories every real UX mistake found with cause/impact/fix, and specifies how that identity rebuilds on this track's own already-frozen platform layer — design tokens, component mapping, navigation grouping extension, the official Lucide-icons decision, and a fully specified Login Experience (Configuration-Platform-sourced branding, dark/light, background image/slider/video, maintenance-mode messaging, a three-step Loading→Bootstrap→Dashboard sequence). Also documents (not implements) the Installation Wizard vs. First-Time School Setup Wizard distinction.

**Design System implementation — small-slice discipline, each slice complete/verified/frozen before the next begins (per explicit instruction, mirroring the backend's own Phase 0/1/2 discipline).** **Phase A (Design Tokens) COMPLETE** — `admin/src/index.css` carries the frozen brand palette (light + dark HSL tokens, ported from §4.1, including the new semantic `--success`/`--warning` tokens the legacy admin never had), the Tajawal/Inter font pairing (self-hosted via `@fontsource`, not the legacy admin's Google Fonts CDN — an implementation-level choice, not a token change), the `soft`/`soft-lg` shadow tokens, and the `fade-in` keyframe/animation utility, all verified live (computed-style checks confirming light/dark token values match the frozen doc exactly, RTL `dir`/`lang` switching, the animation utility resolving correctly once referenced). `admin/src/lib/icon-sizes.ts` codifies the frozen Lucide sizing scale (§19.3) as named constants (`lucide-react` itself needed no dependency change — already present in both codebases, per §19.2's decisive reasoning). One real bug found and fixed during verification: `platform/i18n/index.ts` only applied `document.documentElement.dir`/`lang` inside `setLocale()`, never on initial app boot — a persisted Arabic locale would render `dir="ltr"` on every fresh page load until the user manually re-toggled the language switcher. Fixed by calling the same DOM-side-effect function once at module init. Full verification: Vitest (4/4 passing), `tsc -b` clean, `oxlint` clean (two pre-existing warnings in untouched files), zero browser console errors across light/dark/LTR/RTL.

**Phase B (App Shell) COMPLETE** — built and frozen as its own product, not a layout wrapper, per explicit instruction: every future workspace inherits this shell unchanged. `WorkspaceDefinition` gained an additive, optional `group` field (§8.2) so `SideNav` can cluster related workspaces (Administration's nine child capabilities) under one collapsible header — auto-opening on an active route via a boundary-safe longest-prefix match (a real bug class ported defensively from the legacy admin's own `matchPath()`, since a naive `pathname.startsWith()` would have mismatched e.g. `identity` against `identity-maintenance`), with a session-scoped manual-override reset on navigation and tooltip-on-hover when collapsed (RTL-aware physical-side resolution via `i18n.dir()`, since Radix's `Tooltip` only accepts physical values). New shell primitives: `Breadcrumb` (a genuine multi-level trail — Home → workspace → contributed segments via a `useBreadcrumbSegments` extension point — replacing the legacy admin's own under-built two-level version), `WorkspaceHeader` (the reusable page-header primitive named in §7, closing the exact duplication pattern §M6 flagged before it could start), `QuickActions` (a registry mirroring `WidgetDefinition`'s own shape, renders nothing with zero registered actions, matching the "correct with zero" bar `EmptyWorkspaceState` already set), and `Footer` (version + copyright, the version now build-time-injected from `package.json` via a new Vite `define`, never hand-maintained per §20.6). `TopBar` was rebuilt calm and responsive (breakpoints re-tuned to `lg` matching the legacy admin's own persistent-sidebar threshold) with every element audited for real purpose. `NotificationCenter` and `SearchBar` were made honest per §M1/§M2's fix generalized: zero registered providers now shows a distinct "not connected to any module yet" message, never conflated with a genuine zero-match search, and `SearchBar` results became real, keyboard-navigable (Arrow Up/Down/Enter/Escape) navigation targets instead of inert list items. `CommandPalette` was rebuilt on Radix `Dialog` instead of a hand-rolled fixed-position overlay (§11's rule against hand-rolling when Radix is available), gaining a real focus trap and Escape handling for free. `Badge` and `Skeleton` primitives were added (§6.3's gap list); `Tooltip`/`DropdownMenu`/`Sheet`/`Dialog` all received the frozen shadow/radius/`animate-fade-in` tokens, replacing dead `animate-in fade-in-0 zoom-in-95` classes left over from a Tailwind plugin (`tailwindcss-animate`) that was never actually installed in this codebase -- those classes had silently done nothing.

Real bugs found and fixed during verification (a temporary, fully-reverted fixture registry and a temporary `useVisibleWorkspaces` bypass were used to exercise Sidebar's grouping/active-state behavior against real data, per the same "prove it, don't assume it" discipline as the backend — neither survives in the frozen diff): (1) the Command Palette's `⌘K` trigger button had no accessible name at all (icon + a `kbd` hint with no `aria-label`), found via a real accessibility-tree read, not assumed; (2) a genuine sidebar collapse/expand transition bug — zustand's `persist` middleware rehydrates collapsed state one render tick after the initial default-state paint, and that near-simultaneous style change was confusing the CSS transition's starting reference badly enough that the very first user click after *any* page load (in either direction) silently failed to visually apply, while every subsequent click worked correctly; root-caused by testing three structurally different width-setting mechanisms (Tailwind `calc(var())`-based utility classes, `overflow-hidden`, `min-w-0`, inline plain-rem styles) and observing the identical failure pattern regardless, isolating the cause to timing rather than any specific CSS mechanism — fixed by suppressing the transition class until one `requestAnimationFrame` after mount, so the first paint never animates (correct, since there is nothing to animate from) and only a genuine user-triggered toggle transitions smoothly, verified correct on the true first click across three independent clean server restarts.

Full verification: real login against the live backend (not a mocked session), Sidebar grouping/auto-open/`aria-current`/tooltip behavior confirmed against real fixture data, Breadcrumb's real trail confirmed, collapse persistence confirmed across reloads, dark mode and RTL confirmed (sidebar correctly mirrors to the physical right edge, Tajawal-first font swap, Arabic `aria-label`s), mobile drawer confirmed (hamburger, full-width Sheet, group auto-open, focus-trapped close), Command Palette keyboard shortcut and Escape-to-close confirmed, honest empty states confirmed for both Search and Notifications, zero console errors throughout. `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new), Vitest 4/4 passing. Phases C (Login Experience), D (Dashboard shell), and E (Administration Workspace / General Settings as the reference workspace) remain, each its own slice.

**Phase B revision — icon sizing & radius amendment COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §23 (append-only amendment to the frozen §4.3/§19.3, both triggered by a real usability review, not a reopened redesign discussion). Icon scale moved to `ICON_SIZE.dense/default/prominent` (20/24/28px, up from 16/20/24px) in `admin/src/lib/icon-sizes.ts`, prioritizing readability for the product's actual primary users (school staff spending long hours in the system, a meaningful share needing larger UI elements) over density. Radius moved to four independent flat tokens (`--radius-none/sm/md/lg` = 0/4/6/8px, `admin/src/index.css`) replacing the old `calc()`-derived scale that topped out near 14px — every `rounded-xl`/`rounded-2xl` in the App Shell was remapped to the new ceiling of `rounded-lg` (dialogs, Command Palette) or lower (`rounded-sm` for sidebar nav items and dropdown/palette list items, `rounded-md` for dropdown/tooltip/skeleton containers), producing a flatter, more enterprise-appropriate surface; `rounded-full` was left untouched everywhere as a deliberate, separate design-language choice for genuinely circular elements. One real bug found in the same pass (not merely a size change): `Button` and `DropdownMenuItem`'s `[&_svg]:size-4`-style descendant default has higher CSS specificity than a plain `size-*` class on the icon itself, so `DropdownMenuItem`'s rule was silently overriding `UserMenu`'s explicitly-sized `LogOut` icon — fixed with the same `[&_svg:not([class*='size-'])]:size-N` guard already applied to `Button` in the icon-sizing pass, and confirmed via a real computed-style check (the icon rendered at its intended 20px, not the previously-forced 16px) rather than assumed fixed. Verified live against the running App Shell: real login, token values read via computed-style checks (radius tokens, icon `getBoundingClientRect()` sizes), RTL search-icon/padding mirroring, dropdown/notification/user-menu/Command Palette radius and icon sizes all confirmed post-fix.

**Global Context Model — design FROZEN, not yet implemented (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §24 (a new append-only section, not a supersession of anything in Phase B's shipped code). Reached through a dedicated UX review that explicitly challenged the first proposal (a global "Historical Context" mode with ambient warning chrome and session-wide write risk) before converging on a hybrid: Organization/Branch/Academic Year as one unified Global Context control in the Topbar (not three dropdowns), low-friction browsing with an explicit-but-lightweight inline Switch step for Academic Year (never an instant apply, never a blocking modal), an always-visible Working-vs-System-Active-Year distinction using calm/muted styling (never `--warning`/`--destructive`), and — the central decision — write protection scoped to the mutation itself (permission split, risk-tiered confirmation reusing the existing §M4/§M7/§M9 taxonomy, Approval Engine routing for the top tier) rather than to the context switch. Persistence (session-scoped, reset to system defaults on every fresh login) and Branch/Year validity (auto-correct to the new Branch's active year with a visible, non-blocking notice, never a silent or invalid state) were resolved as explicit first-principles recommendations rather than left open — a dedicated codebase check confirmed no existing Branch-context persistence precedent exists to mirror (`User.php`'s own docblock states the Admin Platform Foundation has no branch-switcher concept today; permissions are computed as a cross-branch union for exactly that reason), so Global Context is the first implementation of this concept for all three dimensions together, not a retrofit onto prior behavior. **Phase B (App Shell, as originally scoped and as shipped in code) is now considered officially complete.** Global Context Model is frozen documentation only — no control, store, or write-boundary guard exists in `admin/` yet — and is tracked as its own future implementation slice, sequenced separately from Phase C (Login Experience), which begins next. A closing scope clause (§24.9) makes explicit what §24.5 already implied but never stated outright: Global Context is a default-working-context UX layer, never an authorization mechanism — selecting a Branch or a historical Academic Year never grants access to it or bypasses `modify-historical-records`, its risk-tiered confirmation, or Approval Engine routing; every mutation is still fully subject to the permission/approval system regardless of what's currently selected as Working Context.

**Phase C (Login Experience) COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §20. Built against real infrastructure only, not speculative Configuration-driven content: the split-screen structure (§20.1, `login-page.tsx` rewritten, `login-brand-panel.tsx` new) is real and complete — brand column collapses into a compact header band under `lg` rather than disappearing (§1.1/§3's confirmed legacy failure), never a full illustration crush. Everything §20.2–§20.4 specifies as Configuration-Platform-sourced (a custom logo, background image/slider/video, bilingual Configuration welcome copy, rotating motivational messages) has no real Configuration schema behind it yet — Digital Experience, the owning capability, is not built — so rather than fake that data, the brand panel renders exactly the spec's own documented fallback for each layer: the product wordmark, the "none" background mode (solid `--primary`, explicitly "always a valid, complete configuration on its own" per §20.3), and static i18n welcome copy; no rotating-message mechanism was built, since an empty array is a fully valid, complete state per §20.4 and there is no real varying content yet to prove a rotation mechanism actually rotates. §20.5's maintenance-mode message, by contrast, is fully real: Laravel's own `PreventRequestsDuringMaintenance` middleware (already-live infrastructure, `php artisan down`) is genuinely detected via `use-maintenance-check.ts` — a lightweight unauthenticated probe against `/workspaces` — and live-tested end-to-end (`php artisan down` / `php artisan up`) rather than assumed working. §20.9's Loading → Workspace Bootstrap → Dashboard sequence is new: `workspace-bootstrap.tsx` gates the entire protected layout behind `/me` and `/workspaces` both resolving, replacing what was previously a silent, uncoordinated hydration inside whichever component called those hooks first, with one deliberate branded transition. §20.6's version/copyright footer is reused inline in the brand panel (not the shared `Footer` component verbatim — its `text-muted-foreground` assumes a light/card background context, wrong on a solid `--primary` panel).

Two real bugs found and fixed during verification, neither assumed away: (1) the compact (mobile) brand panel had no `lg:hidden` wrapper, so it rendered on desktop stacked alongside the full panel — found via a real computed-style check (`getBoundingClientRect()` width) at 1280px, not visual inspection alone; (2) a genuine session-invalidating race: `useMaintenanceCheck` initially reused the shared `apiFetch` client, whose global side effect on *any* 401 is `useAuthStore.logout()` — correct for a real stale session, wrong here, since this probe's own expected outcome during normal operation *is* a 401. If that query was still in flight (or refetched) after a successful login elsewhere, its late-arriving 401 silently wiped the just-set token and bounced the user back to `/login` — reproduced live (a real login attempt landed back on the login form with no visible error), root-caused via network-request inspection, fixed by having the hook issue a plain `fetch()` instead of going through the shared client, so it only ever reacts to the response status, never the shared auth store. Full verification: real login end-to-end (including the exact failure mode above, confirmed fixed by re-running the same scenario), session stability across a reload, real maintenance-mode detection live-tested via `php artisan down`/`up`, wrong-credentials error path, RTL layout (brand column correctly mirrors to the physical start/right side) and Arabic translation (verified via `i18next`'s real `changeLanguage`, not just DOM attributes), dark mode (both panels' independent `--primary`/`--primary-foreground` and `--foreground` contrast confirmed correct via computed styles), mobile collapse (compact header band, full-width form, zero content loss). `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new). One pre-existing, out-of-scope gap found and flagged, not fixed here: `mapServerErrors` surfaces the backend's raw (English-only) validation message verbatim instead of a localized i18n string whenever the server returns field errors, so a wrong-password attempt shows English text even under the Arabic UI — a cross-cutting issue affecting every form using that shared helper, not specific to Login, spun off as its own task rather than patched inline.

**Phase D (Dashboard Shell) COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §25 — a dedicated design pass first, deliberately rejecting a framework-first ("container for future widgets") framing for a user-first one, before any code, matching every prior phase's discipline. `HomePage` becomes the Dashboard shell (extended, not replaced by a second landing page), composing three independently-proven mechanisms rather than one new abstraction: `QuickActions` (existing Phase B registry) gains a second surface here; a new `dashboard-widget-registry.ts` mirrors `WorkspaceDefinition`'s own registration pattern so a future module can optionally contribute a widget, rendered via the already-built `Dashboard`/`WidgetHost` framework from Phase 13 (`registered-widgets.tsx`, zero widgets today, renders nothing — same "correct with zero" bar already proven); `notifications-summary.tsx` is a denser second presentation of the same `useNotifications()` hook and honest empty-state copy `NotificationCenter` already proves. The one deliberately-drawn scope boundary (§25.3, "the Dashboard owns presentation and composition only, every business capability contributes exclusively through registration") kept this phase pure frontend: a capability check found most of the sections a genuinely operational Dashboard would need (My Pending Approvals, Recent Activity) have no backend API today — the Approval Engine has no list/query method at all, Activitylog data is recorded but never exposed — and rather than pull that backend work into this phase, §25.5 explicitly defers it, so this phase shipped infrastructure only, exactly as scoped.

**Phase E — Administration Workspace, design FROZEN (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §26 — a full UX/product-design review (information architecture, navigation, settings hierarchy, page templates, permission model, configuration philosophy, layout patterns, empty states, responsive behavior, accessibility) before any code, matching every prior phase's discipline. Scope was deliberately narrowed during review: Administration is the reference implementation for **configuration-oriented** workspaces specifically, not every future workspace — entity-CRUD workspaces (Students, Finance, HR, Library) are a different shape, already served by the `DataTable`/`Form` frameworks, and need their own reference proof separately. First implemented child (of §8.3's nine-child Administration group): **Configuration Platform**, honoring the already-frozen nav structure without reopening it.

A capability check found the same class of gap Phase D found for Pending Approvals, but more severe: `SettingsResolver`, `ConfigurationRegistry`, and `ProviderManager` are real, tested, feature-complete PHP services with **zero HTTP routes** exposing any of them — not "returns empty," no controller exists at all. Unlike Login's wordmark-style fallback, there is no meaningful default for a workspace whose entire purpose is showing real resolved configuration values. Rather than block on this or quietly expand scope to include backend work, the phase was split into two explicitly-sequenced milestones: **Phase E-A** (frontend infrastructure — layout, navigation, templates, permission-aware rendering, responsive behavior, accessibility, empty states — verified against a temporary, fully-reverted fixture, the same discipline as Phase B's Sidebar and Phase D) and **Phase E-B** (a thin adapter-layer REST API exposing the existing services verbatim, no business-logic changes, sequenced after E-A). §26.13 also froze an API stability principle for E-B: once shipped, the REST contract is what the frontend depends on and must not break without a deliberate versioned change, while the internal services behind it remain free to evolve.

Two additional refinements closed before freeze: (1) "Configuration Platform" stays the architectural capability name; the end-user navigation label is a separate, independently-evolvable UX decision via the existing `labelKey` mechanism, not hardcoded to the architectural name (§26.2); (2) an explicit Registration Principle (§26.12) — Administration's children are discovered exclusively through the existing `WorkspaceDefinition` registry, and the Administration Workspace shell never assumes any specific child is present, rendering correctly whether zero, one, or all nine children are registered — ADR-0015 Decision 4's extension-point discipline applied one level deeper.

Phase E-A implementation begins next; see its own COMPLETE entry below, followed by Phase E-B's.

**Phase E-A (Administration Workspace infrastructure) COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §26. Configuration Platform is now a real, registered `WorkspaceDefinition` under the Administration group (§8.3) — `admin/src/workspaces/administration-configuration/register.ts` registers both the workspace and its own translation namespace eagerly (via `App.tsx`, alongside `registerStaticCommands()`), so `SideNav`/`HomePage` have the label before the workspace is ever opened. §26.2's naming decoupling is real, not aspirational: `key: 'configuration-platform'` is fixed, while `labelKey` resolves to "System Settings" through the workspace's own i18n namespace — verified live, the Sidebar/Dashboard/Breadcrumb all show "System Settings," never the architectural name.

A new `ConfigurationDataProvider` interface (`platform/administration/configuration-provider.ts`) mirrors `SearchProviderDefinition`'s "zero registered = honestly not connected" pattern, deliberately not `NotificationProvider`'s always-empty-mock pattern — the distinction matters because a Configuration Platform with zero providers means the real backend integration (Phase E-B) hasn't shipped, which is a "not connected" state, not a "nothing to show" one (§26.7). The page templates (`configuration-platform-page.tsx`, `settings-category-list.tsx`, `settings-category-detail.tsx`, `setting-field.tsx`, plus a new generic `StickyActionBar` primitive) implement the full two-pane rail+content pattern (§26.3/§26.5), a data-type-keyed field renderer (text/number/boolean/select), altitude-resolution and approval-required badges (§26.4), view-only disable-with-note (§26.6), and the mobile drill-down collapse (§26.10).

A real architectural consequence of §8.3's own "real visibility is server-computed" rule (ADR-0015) surfaced during this phase and is worth recording explicitly: registering `configuration-platform` in the local registry does **not** make it appear anywhere in the running app today, for any user including Super Admin, since `/api/v1/workspaces` still returns `[]` unconditionally — this is correct, expected behavior, not a bug, and is a distinct gap from Phase E-B's *data* API (this one blocks the nav item from appearing at all, E-B's blocks the page's data once you're on it). No backend change was made to work around this, preserving Phase E-A's frontend-only scope exactly as decided.

Full verification used the same temporary-fixture discipline as Phase B's Sidebar and Phase D's Dashboard: `use-visible-workspaces.ts` was temporarily patched to force-include `configuration-platform`, and a fixture `ConfigurationDataProvider` (two categories, one empty, four field types with every `resolvedFrom`/`canEdit`/`approvalRequired` combination) was registered in `App.tsx`, both fully reverted before commit (confirmed via zero `git diff`). Verified live: the not-connected state (real, permanent, provider unregistered), full category/field rendering with correct badges, the edit→save→clear flow (`StickyActionBar` appearing/disappearing correctly), mobile drill-down (rail hidden via `offsetParent === null`, back button present, correct RTL-mirrored `rtl:rotate-180` arrow), RTL layout and real Arabic translation, dark mode contrast, zero console errors. `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new). Phase E-B (the thin adapter-layer REST API) remains, its own separately-scoped task.

**Overview Grid refinement COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §26.15 — an append-only amendment, not a supersession of the two-pane interface itself. System Settings now lands on a new `settings-overview-grid.tsx` (a responsive 3/2/1-column card grid, verified live at exactly those breakpoints) before the rail+detail interface; each card shows an outline icon, name, one status badge (`Ready`/`Needs Setup`/`Error`, reusing the existing `Badge` component's success/warning/destructive variants — no new color system), and an optional one-line secondary note, deliberately modeled on modern Settings-surface precedent rather than a dashboard. `SettingCategory` gained `icon`/`status`/`secondaryLine` as an additive extension of the same provider contract — Phase E-B's scope is unchanged, only the shape of what it eventually returns grew. The back-to-overview affordance now shows on every breakpoint (previously mobile-only), closing a real gap the original two-pane layout had: there was no way back to the rail's starting point from desktop once a category was selected. Verified live using the same temporary-fixture discipline: three fixture categories exercising all three status values and both with/without a secondary line, confirmed correct at desktop/tablet/mobile widths and through the full card→two-pane→back round trip, then fully reverted (zero `git diff` confirmed). `tsc -b` clean, `oxlint` clean.

**Overview Grid visual revision COMPLETE (2026-07-19)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §26.16 — a same-day amendment superseding §26.15's grid-column count and radius statement specifically, driven by an explicit "premium enterprise, Stripe/Linear/Notion/Apple-inspired" visual brief. Grid moved to 6/4/2/1 columns (large desktop/medium/tablet/mobile), verified live at exactly those four breakpoints, superseding the original 3/2/1. Two explicit, scoped exceptions to previously-frozen tokens were made and recorded, not silently drifted: `rounded-none` on these cards specifically (§23.2's enterprise radius scale caps cards at 4–6px, never square — this goes further, on purpose, for this card family only, §23.2 unchanged for every other card in the product) and a hover lift (`translateY(-2px)` + stronger shadow, `transition-[transform,box-shadow]`) on these cards specifically (§4.4 explicitly froze "no scale/translate hover effects anywhere" as a considered rule — this is a deliberate, narrow, named exception, not a reopening of that rule elsewhere). A fourth status value, `disabled`, was added to `SettingCategoryStatus` (a muted badge, reusing `Badge`'s existing `muted` variant, no new color) for a capability that exists in the taxonomy but isn't reachable yet — the card renders non-interactive rather than clickable-but-pointless. Colors and shadow reuse existing tokens verbatim (`shadow-soft`/`shadow-soft-lg`, dark-mode `--background`/`--card`) — no new color or shadow value was introduced, despite the brief's own example shadow value being a different one-off (`0 8px 24px rgba(0,0,0,.18)`); the existing frozen `--shadow-soft`/`-lg` tokens were judged close enough in spirit to reuse rather than add a third parallel shadow value. Verified live: all four breakpoints, the new `disabled` status (non-interactive, reduced opacity, `cursor-not-allowed`), dark-mode card/background contrast, RTL, real Arabic translation, zero console errors — via the same temporary-fixture-then-revert discipline (zero `git diff` confirmed). `tsc -b` clean, `oxlint` clean.

**Documentation reframe, same day**: §26.16 was rewritten (docs-only, no code change) from "two scoped exceptions to §23.2/§4.4" into the **Overview Grid Pattern** — a named, reusable second card treatment for high-density navigation surfaces, sitting alongside standard Cards rather than carving exceptions into them. §23.2/§4.4 are unchanged for every other card and hover interaction in the product; this pattern is explicitly available to any future overview/navigation grid (Provider Registry, Integrations, AI Providers), not just System Settings. The component's own code comment was updated to match.

A precise, previously-uncaptured distinction was formalized and implemented: **System Initialization** (`getRegisteredWorkspaces()`, the local static registry, is empty — no workspace module built into this deployment at all, a deployment-level fact true for every user) versus **Operational Empty State** (the registry is non-empty but the server-filtered visible list is empty for this user — an ordinary permission gap). Conflating these was a real, named risk: telling a fresh installation's own Super Admin to "contact your administrator" mid-setup would be actively wrong, not merely unpolished. A new `SystemInitializationState` component (distinct copy, no "contact your administrator" framing) now fires on the registry-empty signal; the existing `EmptyWorkspaceState` is unchanged and fires only on the permission-gap signal. Verified live using the same "temporary, fully-reverted fixture" discipline Phase B's Sidebar work established: a dummy `WorkspaceDefinition` was registered directly in `registry.ts` and `useVisibleWorkspaces` was temporarily patched to treat it as visible, proving all three branches render correctly (System Initialization → Operational Empty State → full composition with `QuickActions`/`RegisteredWidgets` correctly still rendering nothing while `NotificationsSummary` shows its own empty state) — confirmed via `git diff --stat` showing zero trace after reverting both edits. Full verification: real login, all three states exercised live, RTL and Arabic translation (real `i18next.changeLanguage`), dark mode, zero console errors. `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new).

**Phase E-B (Configuration Platform integration) COMPLETE (2026-07-20)**, per `docs/ADMIN_DESIGN_SYSTEM.md` §26.13/§26.14. Also the first real use of ADR-0023 (Zod-First API Contracts), frozen the same day as a documentation-only commit ahead of this implementation, and treated as the constitutional rule for all API work going forward: `packages/contracts` (`@alphaschool/contracts`, npm workspace) now ships real Zod schemas for the settings domain (`common/errors.ts`, `settings/settings.schemas.ts`/`.request.ts`/`.response.ts`/`.errors.ts`), consumed by both the backend's Pest tests' expectations and the frontend provider — the single public contract, no parallel manual type definitions.

`ConfigurationController` (`app/Modules/Administration/Http/Controllers`) is the thin adapter §26.13 specified: `GET /categories`, `GET /categories/{key}/settings`, `PATCH /categories/{key}/settings/{fieldKey}`, reusing `SettingsResolver`/`ConfigurationRegistry` verbatim, no business-logic changes. `admin/src/workspaces/administration-configuration/real-configuration-provider.ts` replaces Phase E-A's temporary fixture permanently — the first workspace in this project's history where a data provider is genuinely, permanently wired rather than reverted before commit. Per the earlier AskUserQuestion decision to fold it into this phase's scope, `WorkspaceAccessResolver::resolve()` was also rewritten from its Phase E-A placeholder (`[]` unconditionally) to real permission-based logic, since Configuration Platform would otherwise have remained unreachable in the Sidebar regardless of E-B's own work.

Three real bugs found and fixed during implementation, none assumed correct going in: (1) `ConfigurationController`'s first draft imported `App\Modules\Identity\Models\User` directly for its actor type hints, violating ADR-0016 §5's "Administration depends on no other module" rule — caught by the project's own `AdministrationPlatformBoundaryTest` architecture test on a full-suite run, fixed by typing as `Illuminate\Database\Eloquent\Model` instead, matching `SettingsResolver::write()`'s own existing convention; (2) the frontend's speculative `resolvedFrom` union (`'global' | 'branch' | 'user'`, dating to Phase E-A's pre-backend design) does not match reality — `ConfigurationScopeContext`'s own docblock states User Preferences are deliberately a separate, parallel, lower-ceremony mechanism with no altitude representation in this resolver at all — corrected to the real `'default' | 'global' | 'branch'` union, which also fixed a latent bug in `setting-field.tsx` that still branched on the fictional `'user'` case; (3) `writeSetting()`'s original interface signature (`Promise<void>`, no version parameter) predated knowledge of `SettingsResolver::write()`'s mandatory optimistic-locking contract — fixed by adding a required `expectedVersion` parameter and a `Promise<WriteSettingResponse>` return, with `settings-category-detail.tsx` updated to look up each field's current version and surface `ConflictErrorSchema`'s 409 response as an inline error plus a query-invalidating refetch.

A genuine, pre-existing architectural gap was found and deliberately *not* fixed here, being out of this phase's scope: Spatie's `setPermissionsTeamId()` (the team-context setter gating branch-scoped permissions like `identity.configure-otp-settings`) is only ever called from test helper code (`tests/Pest.php`'s `withTeam()`), never from any real middleware or service provider — meaning team-scoped edit permissions are currently unreachable for any real HTTP request outside of tests, since each `php artisan serve` request is a fresh, context-less PHP process. This is tied to the still-unimplemented Global Context/Branch-switcher work (§24), not something Phase E-B introduced. Consequently, the write-success and 409-conflict codepaths were verified the idiomatic way already established in this codebase — Pest Feature tests (`ConfigurationControllerTest.php`, 10/10 passing) using `withTeam()` + `actingAs()` within one PHP process — rather than forced through real HTTP, which cannot currently exercise that branch at all.

Full verification: backend — `php artisan test` 351/351 passing, `./vendor/bin/pint --test` clean, `./vendor/bin/deptrac analyse` zero violations; frontend — `tsc -b` clean, `oxlint` clean (same two pre-existing warnings, none new); live browser E2E as `testuser` (Super Admin) — real login, Overview Grid showing the real `access-governance` category ("Ready" status, no more "not connected" state), category detail showing both real OTP fields (`code_length: 6`, `lifetime_minutes: 5`) with the correct "Using the global default" badge and the view-only note correctly present (edit-gating has no Super Admin bypass, by design), real Arabic/RTL translation of the new real labels, dark mode, zero console errors throughout. Configuration Platform's data provider is now genuinely connected — the first Administration child, and the first proof of the full Phase 1/Phase 2 backend investment reaching an actual user-facing screen.

**Goal:** build the reusable Workspace shell `docs/ADMIN_PLATFORM.md` already specified — navigation, routing, layout, and the dashboard/widget/DataTable/form/modal frameworks — with zero business content, so every future workspace (Identity, Students, Admissions, Academic, HR, Finance, …) becomes installable as a single `WorkspaceDefinition` registration with no platform-layer changes.

**Scope — IN:** `admin/` scaffolded on the frozen stack (React, Vite, TypeScript strict, Tailwind, shadcn/ui, TanStack Router/Query/Table, React Hook Form + Zod, Zustand, i18next); `AppShell` + permission-aware navigation + routing + responsive layout; Dashboard/Widget/DataTable/Form/Modal frameworks; Search, Command Palette, and Notification Center **frontend contracts and UI only**, backed by mock providers; theme system (light/dark + per-organization brand slot); an automated test proving the `WorkspaceDefinition` extension point requires no `AppShell`/nav/routing/layout changes; backend prerequisites — `GET /api/v1/me`, `GET /api/v1/workspaces` (returns `[]` today), `config/cors.php` — all inside Identity, no new backend module.

**Scope — OUT:** every business workspace and screen (Identity, Roles, Branches, or any Domain module UI); the real Notification Engine, Scout-based Search backend, Reporting, and Broadcasting (ADR-0015 Decision 6 — frontend contracts only, real backends are independently future-scoped); a branch/team switcher concept (ADR-0015's Alternatives Considered — `/me` returns the union of permissions across all branch-scoped roles instead).

**Dependencies:** none from the backend Phase sequence beyond Identity's own already-frozen Permission Groups (Sprint 2.3) that `/api/v1/me` and `/api/v1/workspaces` read.

**Deliverables:** see `docs/adr/0015-admin-platform-foundation-frontend-architecture.md` for the full architecture; `docs/developer/admin-platform-frontend.md` (written at milestone close, mirroring the backend's contract-governance docs).

**Definition of Done:** the shell renders correctly with zero registered workspaces (the primary acceptance criterion, not an edge case); a synthetic test-only workspace proves the registration contract with no platform source changes; every one of the sixteen subsystems named in the approved execution plan has a working, reviewable example; no workspace/business code exists anywhere in the diff.

**Git Milestone:** `v1.0-admin-platform-foundation`

---

## Foundation Track: Administration Platform

**Status: Architecture frozen 2026-07-14** (`docs/adr/0016` through `0022`, `docs/ADMINISTRATION_PLATFORM.md`, `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md`, including a same-day append-only amendment pack closing five findings from a dedicated pre-implementation critical review). **Phase 0 (Formalization) COMPLETE** — see `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md`'s own Phase 0 entry; `app/Modules/Administration` exists with a proven boundary architecture test. **Phase 1 (Configuration Platform Core + Developer Enablement) COMPLETE** — see the Playbook's own Phase 1 entry; the Configuration Registry, `SettingsResolver`, and the full Developer Enablement deliverable set are live, with Identity's OTP settings as the proof consumer. **Phase 2 (Provider Registry & Credential Vault) COMPLETE, backend only** — see the Playbook's own Phase 2 entry; the Provider Registry, the Credential Vault, and three fundamentally different proof providers (SMTP, Google OAuth, Firebase Push) are live, with Media's disk-tier selection retrofitted as the mandated Definition-of-Done consumer. The frontend Configuration Workspace (Phase 2's stated exception to backend-freezes-before-UI) was deferred, not built in this pass. Not a numbered backend Phase and not gated by one — a parallel Foundation-tier track, the same relationship Frontend Track F1 has to the Phase sequence above.

**Goal:** replace the Blueprint §1/Addendum E1 "Settings resolution" charter's deferred internal design with a full Administration Domain Model — ten durable capabilities, a Configuration Platform, an Integration Platform, an Effective-Dated Business Policy pattern, and a derived Administration Experience Layer — engineered to remain correct for fifteen-plus years without Administration Platform ever becoming a God Module.

**Scope — IN (architecture, complete):** the five-question / ten-capability / four-axis model; the Configuration Platform's registration contract, resolver, and fifteen-field metadata model; the Integration Platform's four mechanisms (Provider Registry, Webhook Gateway, Sync/ETL, outbound API Platform); the Effective-Dated Business Policy pattern; the Administration Experience Layer (Search, Dependency Graph, Health/Score, Readiness, Diff, Packages/Snapshots, Import/Export, Rollback, Environment Promotion); the Registry Pattern and Content Lifecycle Pattern, added to the Blueprint's shared-pattern catalog; seven binding delivery principles (ADR-0022) governing implementation sequencing.

**Scope — OUT (deferred to their own sessions, never decided speculatively here):** the White-Label / multi-tenancy commercial-model decision — deliberately treated as a *consumer* of this architecture (via the Altitude axis), not a prerequisite to it; Asset & Facility Stewardship / Infrastructure Administration, needing its own dedicated design session before any phase can be scoped; Website's Digital Experience surface, needing the same.

**Dependencies:** none from the backend Phase sequence — consumes Identity's already-frozen Permission Groups and Core's already-frozen Approval/Audit engines, exactly as Frontend Track F1 does.

**Deliverables:** `docs/ADMINISTRATION_PLATFORM.md` (the Blueprint), `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` (Phase 0–7 execution schedule, dependency graph, risk ranking), `docs/adr/0016` through `0022`.

**Definition of Done (architecture):** every ADR in the series Accepted; the Playbook's Phase 0 boundary architecture test specified and ready to be written as the first real implementation act.

**Definition of Done (Phase 1, first real implementation milestone):** see `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md`'s Phase 1 — Identity's OTP settings live through the Configuration Platform, Developer Enablement deliverables (SDK, helpers, docs, worked example) complete, zero UI.

**Git Milestone:** Phase 0 and Phase 1 both committed locally (no push); a Git tag is deferred until Phase 2 (the next hard sequencing boundary, ADR-0022 §1) is also complete.

---

## Phase 4 — Admissions + Enrollment

#### Sprint 4.1 — Academic Year & Grade Level catalog

**Goal:** the lightweight prerequisite catalog Admissions actually needs (Addendum A1) — not the full Academic module.

**Scope — IN:** `AcademicYear` (own lifecycle including `closed` state, enforced at the policy layer per Addendum A8/B — not just a documented convention); `GradeLevel` global catalog + branch-availability join.

**Scope — OUT:** Sections, Timetables, Subjects, Attendance, Grades — all of Phase 5, not needed yet.

**Dependencies:** Phase 2 (Branch must exist).

**Deliverables:** `academic_years`, `grade_levels`, `branch_grade_levels` migrations + models; a policy enforcing "no new/modified records against a closed Academic Year" as an actual guard, not a comment.

**Definition of Done:** attempting to create a record scoped to a closed Academic Year is rejected by a policy check, proven by a test — this is the concrete enforcement the Blueprint named as still-missing "teeth" for the historical-integrity promise.

**Testing checklist:** the closed-year rejection test is the important one here.

**Git Milestone:** `v1.2-academic-year-catalog`

#### Sprint 4.2 — Applicant aggregate + admission workflow

**Goal:** the Applicant lifecycle (Blueprint §9, Addendum on Admissions) through to a payment-pending decision.

**Scope — IN:** `Applicant` aggregate (`person_id`, `branch_id`, `academic_year_id`, `applied_for_grade_level_id`, `submitted_by_guardian_id`, status machine submitted→under_review→tested→accepted/rejected); `AdmissionAssessment` child entity; Application Number via the Number Generator (a distinct identifier space from Student Number, per the explicit original decision); guardian root-of-trust verification (first-child document check) and step-up authentication (OTP to a verified contact) for the sensitive "submit application" action.

**Scope — OUT:** payment/conversion (next sprint); fee calculation detail (Finance doesn't exist yet — stub via a minimal `Billable`-shaped interface, real implementation arrives in Phase 7).

**Dependencies:** Sprint 4.1, Phase 3 (duplicate-resolution is directly exercised here — a returning guardian's new application must correctly find their existing Person/Guardian record).

**Deliverables:** `Applicant`/`admission_assessments` migrations + models; guardian verification service (root-of-trust + step-up OTP); Application Number sequence registered with the Number Generator.

**Definition of Done:** a returning guardian's second application correctly reuses their existing Person/Guardian record via duplicate detection, without re-requiring document verification; an application submission without a valid OTP is rejected; an application by a brand-new guardian correctly triggers the full root-of-trust document check.

**Testing checklist:** feature tests for both the first-time and returning-guardian paths — these must be two explicit, separate tests, since conflating them was exactly the risk named in the original Admissions session.

**Git Milestone:** `v1.3-admissions-applicant`

#### Sprint 4.3 — Fee trigger, payment, conversion

**Goal:** the synchronous conversion action (Blueprint §9) that creates a Student and its first Enrollment together.

**Scope — IN:** `RegistrationFeeCalculated`/`ApplicationPaymentCompleted` events; the minimal `Billable` stub interface (Finance's real implementation is Phase 7 — this sprint only needs a placeholder that records a fee amount and a "paid" flag, not real invoicing); `ConvertApplicantToStudentAction` — synchronous, transactional, guards against double-conversion.

**Scope — OUT:** real Finance invoicing (Phase 7) — this is intentionally the thinnest possible stub that unblocks the conversion flow without pretending to be Finance.

**Dependencies:** Sprint 4.2.

**Deliverables:** `ConvertApplicantToStudentAction`; the `Billable` stub interface + its placeholder implementation; `StudentEnrolled` event dispatch (with no real subscribers yet beyond a logging listener, since Finance/Library/Transportation/Notifications-as-full-features don't exist yet — but the event contract exists so those modules only need to add a listener later, not touch this action).

**Definition of Done:** converting a paid, accepted Applicant produces exactly one Student and exactly one Enrollment, atomically; attempting to convert the same Applicant twice is rejected, tested explicitly (this is the double-conversion guard named as a real invariant in the Blueprint, and it must be proven under a concurrent-attempt test, not just a single-threaded one).

**Testing checklist:** double-conversion concurrency test (two simultaneous conversion attempts against the same Applicant); event-dispatch test confirming `StudentEnrolled` fires with the correct payload shape for future listeners to rely on.

**Risks:** building a "real-enough-looking" Finance stub that later becomes load-bearing technical debt (Phase 7 discovers half of Finance was accidentally already built as a stub and has to be reconciled) — keep the stub deliberately, visibly thin.

**Git Milestone:** `v1.4-admissions-conversion`

#### Sprint 4.4 — Enrollment aggregate

**Goal:** Enrollment (Blueprint §9/Addendum on Student Academic Lifecycle) as its own aggregate, with the section-assignment and suspension sub-tiers.

**Scope — IN:** `Enrollment` aggregate (`student_id`, `academic_year_id`, `branch_id`, `grade_level_id`, status machine, `previous_enrollment_id`/`next_enrollment_id` chain); `section_assignment` sub-history (no `Section` model yet — Phase 5 — so this is schema-ready but has no real sections to assign to until then); `suspension_records` sub-history; `students.current_enrollment_id` pointer, maintained transactionally.

**Scope — OUT:** actual promotion/repetition/transfer/graduation workflows (those are Phase 5–6 features that *use* Enrollment — this sprint only builds the aggregate and its state machine, not the business processes that drive transitions, beyond what the conversion action in 4.3 already exercises).

**Dependencies:** Sprint 4.3 (created by the conversion action).

**Deliverables:** `Enrollment`/`section_assignment`/`suspension_records` migrations + models; `current_enrollment_id` pointer maintenance logic; composite indexes (`student_id, status` and `branch_id, academic_year_id, status`) per the performance risk named in the Blueprint's final review.

**Definition of Done:** the indexes named above exist and are proven to be used (via `EXPLAIN`) by the "list current students" query shape this system will run constantly; a repeated grade produces two genuinely separate Enrollment rows, each independently queryable.

**Testing checklist:** index-usage verification (not just existence — an unused index is a false sense of safety); repetition-scenario feature test (two Enrollment rows, same grade, different academic year, correctly chained).

**Git Milestone:** `v1.5-academic-enrollment`

**Phase 4 production-readiness checklist:** see below. **A real pilot customer could plausibly go live once Phase 4 is production-ready** — this is the first point in the roadmap where that's true, and staging/deployment infrastructure should be fully proven by this point (see CI/CD Timeline).

---

## Phase 5 onward — Epic-level only (full sprint planning deferred to a dedicated pass per phase)

| Phase | Epics (indicative, not sprint-final) | Key dependency | Key risk to watch for |
|---|---|---|---|
| **5 — Academic build-out** | Sections/Classes; Timetables; Attendance; Grades + Grading Scale (versioned per Addendum A4); Homeroom/Subject Teacher Assignments (via the Assignment pattern); Report Card generation + finalization snapshot | Phase 4 (Enrollment) | Building Grading Scale as a flat setting instead of a properly versioned entity — this was explicitly flagged as needing real versioning, not just a snapshot |
| **6 — HR** | `Employment` aggregate (mirrors Enrollment, Addendum B2); Position/Salary history *within* Employment, not flatly on Employee; Assignment instances (Bus Driver, Committee Member, etc.) | Phase 2 (Employee shell) | Reintroducing Position/Salary as direct Employee children instead of nesting under Employment — this is a named, specific regression risk given the correction only happened late in Phase 1 architecture design |
| **7 — Finance** | Invoice/Journal aggregates (immutable after posting); Fee Plan versions; Billing Policy entities (Sibling/Employee Discount, Scholarship via Approval Engine, Late Fee, Installment) owned by Finance, consuming Household data via People's public service; real `Billable` implementation replacing Phase 4's stub; gapless Number Generator mode for real invoice numbering | Phase 4 (stub `Billable` interface already exists) | Treating Billing Policies as one generic "Policy Version" table instead of several small, properly-typed entities — explicitly rejected in the Blueprint as a God-Object risk |
| **8 — Inventory / Library / Transportation / LMS / Reporting** | Each independently — see Parallel Development Strategy below | Phase 2 (People) + Phase 5 (Academic, for Library/Transport's Student linkage) | Any of these reaching directly into another's tables instead of through events/contracts — this is exactly what `deptrac` exists to catch, and by this phase it has real teeth |
| **9 — Maintenance / CRM** | Undesigned — requires its own architecture session (Family received one; these deserve the same treatment) before any sprint planning | Varies | Skipping the design session and improvising architecture mid-sprint — explicitly against the "no redesign without an ADR" rule now in force |

---

## Designed, Not Yet Scheduled

A dedicated architecture session (2026-07-12, after Phase 2 froze as `v0.7-people-contexts`) produced a frozen design for three concerns not yet assigned a sprint number — the same "design session ahead of its sprint" sequencing already used for Family ahead of Sprint 2.5. Full detail: `docs/DOMAIN_BLUEPRINT.md` Addendum E; `docs/adr/0011`–`0013`; `docs/developer/administration-platform-and-communications.md`.

| Concern | Layer | Earliest plausible trigger | Why not scheduled yet |
|---|---|---|---|
| **Administration Platform** | Foundation (new) | Whenever the first real consumer needs Settings/Custom-Fields/Favorites/Import-Export/Audit-Retention as a shared service, rather than a one-off | No Domain module has shipped yet that actually needs any of these — building it now would be prediction, not promotion, the same caution already applied to Custom Fields (Addendum D1) |
| **Notification Engine (Channel/Provider architecture)** | Foundation (Notifications, already named §1) | Phase 3 (Identity Maintenance's step-up-auth OTP delivery, already stubbed pending this) or Phase 4 (Admissions, per the Blueprint's own note that "Notification + Approval engines are needed once Admissions and HR-adjacent workflows exist") | Real implementation naturally lands once a real transactional trigger exists, not before |
| **Communications** | Domain (new) | The first genuine audience-broad, cross-module messaging need (a broadcast/campaign use case), likely Phase 4+ once Admissions/Academic have real audiences to compose | Needs at least one Domain module's `Audienceable` contract to exist as a real consumer first |

**This does not change Phase 2's sequence.** Sprint 2.5 (Family relationships) remains the next scheduled sprint, unaffected by and independent of this design work.

---

## Parallel Development Strategy

**Phases 0–4 are strictly sequential.** They form one dependency chain (tooling → Core → identity substrate → identity integrity → first real business workflow) and splitting them across multiple developers mostly creates integration risk without real speed-up, since each phase's output is a hard input to the next. Best resourced as 1–3 developers working closely, not parallelized.

**From Phase 5 onward, parallelization becomes safe** — specifically *because* the module-boundary architecture (events + contracts, enforced by `deptrac`) was designed to make this possible:

| Can run in parallel once their shared prerequisite is done | Must stay sequential relative to each other |
|---|---|
| Academic, HR, Finance (once Phase 4 is done — Finance's stub `Billable` lets it start before Academic/HR are fully done, using the same interface it'll later share with them) | Employment (Phase 6) blocks nothing in Academic — they're independent Domain modules by design |
| Inventory, Library, Transportation, LMS, Reporting (once People + basic Academic exist — five genuinely independent teams) | None of these five have a legitimate dependency on each other — if one appears to need another, that's a module-boundary violation to flag immediately, not build around |
| Maintenance, CRM | Both blocked on their own design sessions first, but not on each other |

A ten-person team reaches its natural parallelization ceiling around **Phase 8**, where up to five independent module teams can run simultaneously. Before that, adding people faster than the sequential chain allows mostly produces idle time waiting on Phase 2–4's identity substrate to stabilize — this is worth stating plainly to whoever is planning headcount ramp-up.

---

## Technical Debt Register

Deliberately postponed, with the reasoning that makes it a decision rather than neglect:

| Item | Deferred until | Why |
|---|---|---|
| Full configurable Workflow Engine | A second real workflow-needing feature exists (post-Phase 4) | Building it generically against only Admissions risks guessing at an abstraction that doesn't fit the second real case. Admissions ships on a simple, hardcoded state machine first; the engine generalizes once there's a second data point. |
| MFA / 2FA for Employee accounts | Before first production go-live, but not in Phase 2 | Named as an explicitly open decision in the Blueprint (§16) — needs a product decision (which roles require it) before it's an engineering task, not an architecture gap. |
| Impersonation ("login as") | When a real support/ops need arises | The audit-trail placeholder (`impersonated_by`) is cheap to reserve now; the feature itself has no consumer yet. |
| Meilisearch | Real data volume + an observed search-quality complaint | Scout's `database` driver ships from day one specifically so this swap is a config change later, not a rewrite — building Meilisearch infrastructure speculatively would be premature. |
| Multi-currency ledger mechanics | Finance module design (Phase 7), if a real customer needs it | `Money` exists now with currency awareness, but FX-rate-at-transaction snapshotting and multi-currency journal postings are a Finance-specific design question, not a Core one. |
| Hijri calendar display | UI/localization work, whenever it's scheduled | Confirmed as display-only, computed from stored Gregorian dates — no backend dependency, genuinely safe to defer. |
| Full Document Governance parameter UI (per-collection retention/versioning configurable by an admin, not just by a developer) | Once 3+ modules have real documents with genuinely different retention needs | Code-defined retention per collection is sufficient until there's a proven need for non-developers to adjust it — consistent with the "promotion not prediction" rule applied to UI investment, not just Core code. |
| Larastan level ratcheting past the Phase 0 baseline | Ongoing, revisited every few phases | Jumping straight to the strictest level on day one against an empty codebase is trivial and not informative — ratchet it as real code accumulates and the team's fluency with the tool grows. |
| **`Branch` and `Role` lack a physical-deletion guard** (found during Sprint 2.5's `RelationshipType` strengthening, 2026-07-12) | Whenever Sprint 2.3's frozen work is next touched for an unrelated reason — not a standalone sprint | Both are documented as "deactivate via `is_active`, never delete" (Sprint 2.3), but neither actually refuses a plain `->delete()` call at the model layer — the policy is enforced by convention only, the same gap `RelationshipType` had before Sprint 2.5 added a `deleting()` guard + negative test. Not fixed now because Branch/Role belong to already-frozen Sprint 2.3 work, out of scope for Sprint 2.5 — this entry exists so the gap is a recorded decision, not a silently-carried risk. |
| **`ReasonCode` (Core, Sprint 1.1) has the identical unenforced-deletion gap** (found during Sprint 2.5 Step 2's self-review, 2026-07-12) | Whenever Core's `reason_codes` is next touched for an unrelated reason | Same category as Branch/Role above — `is_active` exists, but nothing stops a physical `->delete()`. `guardian_student.reason_code_id` uses `restrictOnDelete()` at the DB level regardless, so a referenced row can't actually be deleted today, but an *unreferenced* one still can be, silently, with no guard. Not fixed now — `ReasonCode` is Core, frozen since Sprint 1.1, out of scope for a People-module sprint. |
| **`household_members`/`billing_group_members` assume single-current-membership** (found during Sprint 2.5 Step 4's closing review, 2026-07-12) | If/when a real business need for historical membership tracking arises (a person leaving and rejoining a household, tracked as distinct periods rather than one overwritten fact) | Both pivot tables carry a unique constraint on the FK pair (e.g. `household_id`+`person_id`), assuming membership is a single current fact — join or leave, not a history of periods. Verified (via a throwaway `Pivot`-model proof, not just reasoning) that promoting either pivot to a first-class model with additional columns (role, joined-at, approval workflow, metadata) requires no schema change today, since both already carry their own `id()` primary key and `timestamps()`. The one exception: genuine multi-period history would need the unique constraint loosened — itself a normal additive migration, not a breaking one, but recorded here rather than silently assumed away. |
---

## High-Priority Core Architecture Backlog

Unlike the Technical Debt Register above (deliberately postponed, low-urgency items), the two entries below are promoted to their own section deliberately — both are real, found-in-production-code gaps in `App\Core\Concerns\HasTemporalAssignment` (Sprint 1.1, frozen), surfaced only once `guardian_student` (Sprint 2.5 Step 2) became the trait's first real consumer. Neither was patched locally in `guardian_student` on purpose: a single consumer working around a shared Core trait's own gap would leave every future consumer (Enrollment, Employment, and every other Assignment-pattern table named in §7) to rediscover and re-fix the identical problem independently — the same reasoning already applied to the `Branch`/`Role`/`ReasonCode` deletion-guard gaps, but higher priority here because both affect data-integrity guarantees the trait's own contract already claims to provide.

**Task: `HasTemporalAssignment` concurrency safety + date-boundary normalization (Core, Sprint 1.1 infrastructure — not Sprint 2.5).**

1. **Concurrency safety.** `guardAgainstOverlap()` is a fetch-then-check-then-write inside an Eloquent `saving()` hook — no row lock, no database-level exclusion constraint. Two concurrent requests creating overlapping periods for the same scope could both pass the check before either write lands, unlike `NumberGeneratorService`'s already-proven `lockForUpdate()` handling. Fix: wrap the competitor-fetch + overlap-check + save in a transaction with `lockForUpdate()` scoped to `temporalScopeAttributes()`.
2. **Date-boundary normalization.** The trait documents `effective_from`/`effective_until` as `date`-typed and `scopeAsOf()` compares against `Carbon::parse($date)->startOfDay()`, but nothing in the trait enforces that a consumer's stored values are actually day-boundary-normalized — Eloquent's `date` cast only truncates on *display*, not on the raw stored value. `GuardianStudent` (Sprint 2.5 Step 2) found this the hard way and carries a local, model-level mutator fix (`setEffectiveFromAttribute`/`setEffectiveUntilAttribute`) as a stopgap. Every future `HasTemporalAssignment` consumer shares the identical day-granularity semantics (Enrollment, Employment, teacher/committee/route assignments, Fee Plan versions all read as dates, never times, throughout the Blueprint) — this is a missing responsibility of the shared abstraction, not a `GuardianStudent`-specific concern. Fix: move the normalization into `HasTemporalAssignment` itself (e.g. inside `bootHasTemporalAssignment()`), so it is guaranteed centrally rather than re-implemented per consumer.
3. **Sequencing:** implement both together — they touch the same `saving()` hook and the same trait, and splitting them into two separate changes risks two separate migrations/reviews of the identical code path.
4. **Cleanup:** once the Core fix ships, remove `GuardianStudent`'s local `setEffectiveFromAttribute`/`setEffectiveUntilAttribute` mutators — they become redundant, and leaving them in place after the trait guarantees the same thing centrally would silently mask whether the Core fix actually covers this model too.
5. **Proof standard:** both must be proven the same way `NumberGeneratorService`'s concurrency safety was — a genuine dual-connection/dual-process test, not a sequential-loop stand-in — plus a test proving a same-day, post-midnight-created row is correctly included in `active()`/`asOf(today())`.

---

## CI/CD Introduction Timeline

| Capability | Introduced at | Why then, not earlier or later |
|---|---|---|
| Pint, Larastan (baseline), `deptrac`, Pest (unit/feature/arch), CI pipeline, branch protection | **Phase 0, Sprint 0.1.1** | Non-negotiable, day one — retrofitting onto existing code is far more expensive than starting with it (see Phase 0's own Risks). |
| Dependency/vulnerability scanning (`composer audit`, Dependabot-equivalent) | **Phase 0** | Cheap, continuous, no reason to wait. |
| Containerization (Docker for local dev + CI parity) | **Phase 0–1** | Same "cheap now, expensive later" logic used throughout the architecture itself, applied to developer environments — waiting until multiple developers have diverged local setups makes this materially harder. |
| Staging deployment pipeline | **End of Phase 2** | Once there's a real, demoable slice (login + identity management), get it deployed somewhere real to surface deployment issues before go-live pressure exists, not during it. |
| Monitoring / structured logging / error tracking (e.g. Sentry, Laravel Pulse) | **Phase 1–2** | Directly required by the Blueprint's own flagged, still-open risk (Addendum B9: eventual-consistency reconciliation, listener idempotency) — you cannot build a dead-letter/reconciliation mechanism without observability already in place. This is executing against a named architectural risk, not a generic best practice. |
| Mutation testing | **Phase 3 (Identity Maintenance)** | Too noisy and expensive to be useful against a still-rapidly-changing early codebase; Identity Maintenance's Merge/Anonymization logic is the highest-stakes code in the system and the natural first target for verifying tests actually catch real mutations, not just achieve coverage. |
| Performance/load testing | **Phase 4 onward** | Admissions is the first real external-facing, concurrency-sensitive workflow (payment/conversion, Number Generator contention) — the first point where load testing has something real to test. |
| Production deployment pipeline, finalized | **Before Phase 4 completes** | This is the earliest plausible point for a real pilot customer, per Phase 4's own note above. |
| Security testing (SAST, then a real penetration test) | Dependency scanning from Phase 0; deeper SAST/pen-testing **before the first production customer go-live** | Pen-testing is expensive and time-boxed — most valuable against a stable, feature-complete-enough surface, not a moving target. |

---

## Documentation Discipline

| Artifact | Updated when | Not updated when |
|---|---|---|
| `docs/DOMAIN_BLUEPRINT.md` | Only when an approved ADR changes something frozen | Ordinary feature work — it's frozen, and staying frozen is the point |
| `docs/adr/*` | A genuine gap or ambiguity in the frozen Blueprint is discovered during implementation (should be rare post-freeze) | Routine implementation decisions that don't touch a frozen item |
| API docs (Scramble) | Every sprint that adds/changes an endpoint — auto-generated, spot-checked | — |
| `docs/developer/*` | A new shared pattern/convention is introduced (e.g. adopting `HasTemporalAssignment`, implementing the Identity Maintenance contracts) | Ordinary business-logic changes within an already-documented pattern |
| User-facing docs | From Phase 4 onward, once there's a real registrar/guardian-facing workflow to document | Phases 0–3 (nothing user-facing exists yet) |
| `CHANGELOG.md` | Every sprint, every merged PR of consequence | — |

---

## Engineering Discipline — What NOT to Build Yet (consolidated)

| Module/Phase | Do NOT build yet | Build when |
|---|---|---|
| Identity | MFA, SSO/OAuth, impersonation, advanced session-risk scoring | Before production go-live (MFA), or when a real consumer need arises (the rest) |
| Identity Maintenance | Cross-module domain vetoes with real implementations (Finance/HR-specific `canReassignPerson` logic) | As each owning module (Finance, HR) is actually built — the contract point exists now, implementations arrive later |
| Media | Per-collection retention/versioning admin UI, OCR/AI hooks, digital signatures | Once 3+ real consumers exist (Document Governance UI) or a real integration need arises (OCR/AI/signatures) |
| Admissions | Configurable Workflow Engine | After a second real workflow-needing feature exists to validate the abstraction against |
| Finance | Multi-currency ledger mechanics | If/when a real customer needs it — `Money` is ready, the ledger design isn't required until then |
| Search | Meilisearch | Real data volume + an observed pain point, not speculatively |
| All modules | Anything not already named as a Domain module in the Blueprint (no inventing new modules mid-sprint) | Never, without an ADR |

---

## End-of-Phase Production-Readiness Checklists

### End of Phase 0
- [ ] CI green on every merge for at least 2 consecutive weeks with no red-then-ignored failures
- [ ] `deptrac` and Larastan both proven (not just configured) to catch real violations
- [ ] Every frozen Blueprint decision has a linked ADR

### End of Phase 1 (Core)
- [ ] Number Generator concurrency test passes under realistic simulated load, not just a toy case
- [ ] Private-media access control proven with a real unauthenticated-request test
- [ ] `HasTemporalAssignment` adopted with zero deviation by every consumer built so far

### End of Phase 2 (Identity & People)
- [ ] Multi-context-per-Person scenario (Employee + Guardian simultaneously) passes as an explicit test
- [ ] Branch-scoped role isolation proven (assign in A, no access in B)
- [ ] Super Admin bypass proven against a branch created after the bypass logic existed
- [ ] Staging deployment live and reachable
- [ ] Arabic kinship-term distinction in `person_relationships` proven, not just theoretically supported

### End of Phase 3 (Identity Maintenance)
- [ ] Full merge lifecycle (preview → dry run → approval → execute → rollback) passes end-to-end
- [ ] No-self-approval rule proven even for Super Admin
- [ ] Activitylog-redaction ADR exists and its resolution (implemented or explicitly deferred) is documented
- [ ] Contract-declaration architecture test proven to catch an undeclared reference

### End of Phase 4 (Admissions + Enrollment) — first plausible pilot-customer go-live point
- [ ] Closed-Academic-Year rejection proven as an enforced policy, not a convention
- [ ] Returning-guardian and new-guardian application paths both explicitly tested
- [ ] Double-conversion guard proven under a concurrent-attempt test
- [ ] Enrollment composite indexes proven used via `EXPLAIN`, not just present
- [ ] Production deployment pipeline finalized
- [ ] Security dependency scanning clean; SAST pass completed if this is the actual go-live point for a real customer
