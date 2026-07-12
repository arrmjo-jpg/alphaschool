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

**Goal:** the module-contract discipline (Addendum C11) is enforced by CI, and the Duplicate Resolution workflow (distinct from the Detection algorithm built in Phase 2) is live.

**Scope — IN:** architecture test scanning every module's schema for columns plausibly referencing Person (`*_person_id`, `student_id`, `employee_id`, `guardian_id`) and failing CI if a module hasn't declared its contract status; `DuplicateFlag` workflow (review a flagged candidate pair, resolve as merge-candidate or dismiss); Identity Governance Permission Group.

**Scope — OUT:** Merge execution itself (next sprint); Anonymization (Sprint 3.3).

**Dependencies:** Phase 2 complete (needs Person, Employee/Student/Guardian, and their contract declarations to scan).

**Deliverables:** `tests/Architecture/IdentityContractDeclarationTest.php`; `DuplicateFlag` model + resolution workflow; Identity Governance permission group + seeded permissions.

**Definition of Done:** the architecture test genuinely fails when a deliberately-added column is left undeclared, proving the safety net works before it's ever relied on for real.

**Testing checklist:** the contract-declaration architecture test's own negative case (prove it catches an undeclared reference) is the critical test here.

**Git Milestone:** `v0.9-identity-maintenance-detection`

#### Sprint 3.2 — Merge: Preview, Dry Run, Execute, Rollback

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
