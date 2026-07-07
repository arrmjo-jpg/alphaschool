# Employee, Student, Guardian Context Shells (Sprint 2.4)

The three context aggregates that reference Person (`docs/DOMAIN_BLUEPRINT.md` §3) now exist, deliberately as bare shells — no Enrollment, no Employment, no `guardian_student`, no numbering. This sprint was preceded by a full design review, a Decision Record, and a per-step readiness/approval cycle; this document records what was actually built, not the review itself.

## One shape, three models

`Employee`, `Student`, and `Guardian` are structurally identical: a unique one-way `person_id` FK, a coarse `lifecycle_status`, a ULID `public_id` (Addendum D4), `SoftDeletes` (not `is_active` — see below), `LogsActivity`, and trivial `ReassignsIdentityReferences`/`RedactsPersonalData` implementations mirroring Person's own Sprint 2.1 precedent exactly. Each was built as its own commit, reviewed independently, before moving to the next.

**Why `SoftDeletes`, not `is_active`:** this is the one distinction worth restating precisely, since Sprint 2.3 just established the opposite treatment for Branch and Role. The original pre-implementation design session's own three-way soft-delete taxonomy places "Students, Employees" (and, by direct analogy, Guardian) in the *true soft-delete* category — identity-context aggregates where historical existence has compliance weight and restoration is a plausible workflow — the same category as Person and User. Branch and Role are *reference/structural* entities (Team-scoping units, role definitions) where a "deleted" row must never leave dangling FK/pivot references — a different category entirely. Both rules are correct; they just apply to different kinds of tables.

**Why Guardian's `lifecycle_status` stays flat (`active`/`inactive`) while Student gets a third value (`graduated`/`withdrawn`):** Guardian's real substance lives in `guardian_student` (Sprint 2.5), not in Guardian itself — its lifecycle is genuinely thinner, and was deliberately not widened to match Employee/Student for symmetry's own sake. Symmetry across the three shells was a named risk to resist, not a goal.

## The Student.lifecycle_status / Enrollment.status boundary

See ADR-0010 for the full reasoning. In short: `Student.lifecycle_status` answers exactly one question — does this Person currently have some active enrollment at all, in/out — and is a *derived mirror*, never independently editable. Only four events may ever change it (first admission, graduation of the current enrollment with nothing following, withdrawal of the current enrollment with nothing linked, re-admission). Everything else — promotion, repetition, transfer, suspension, section changes, academic-year rollover — touches only the future `Enrollment.status`, never Student. Suspension in particular will never become a fourth `Student.lifecycle_status` value, even if the business wants it visible somewhere prominent (report cards, portal) — that visibility is a read-model concern over Enrollment, not a reason to widen Student's own enum.

## The Identity Maintenance contract boundary

See ADR-0009 for the full reasoning. In short: `Employee`/`Student`/`Guardian`'s `reassignPerson()`/`anonymizePerson()` implementations are deliberately dumb — an unconditional data move, a no-op redaction. They assume, and are entitled to assume, that Identity Maintenance (Phase 3) has already (a) validated that the reassignment won't violate a structural constraint (e.g., both the losing and winning Person each already holding one of these rows), and (b) guaranteed the call happens at most once per successful merge, regardless of queue retries, worker crashes, duplicate events, or manual retries. Neither guarantee exists in code yet — Identity Maintenance doesn't exist until Phase 3 — this is the contract it must honor when it's built, not an active guarantee today.

## Contract declaration is proven, not assumed

`tests/Architecture/IdentityMaintenanceContractDeclarationTest.php` asserts `Person`, `Employee`, `Student`, and `Guardian` each implement both contracts, and was proven to actually catch a violation (one interface was temporarily removed from `Guardian`, the test failed, the interface was restored) — the same negative-test discipline already applied to `deptrac` (Sprint 0.1) and the direct-permission-grant check (Sprint 2.3). This is the lightweight, per-model version of Addendum C11's declaration requirement; the fuller schema-scanning mechanism (catching an *undeclared* Person-referencing column on any future module automatically) is Sprint 3.1's job.

## Multi-context-per-Person is proven, not theoretical

`tests/Feature/People/MultiContextPersonTest.php` is the concrete proof of ADR-0001's central claim: one Person can hold Employee, Student, and Guardian simultaneously, each with a fully independent lifecycle. This is the sprint's headline Definition of Done item.

## `AccountTypeResolver` is now real

`App\Modules\Identity\Services\AccountTypeResolver::resolve()` performs three plain, unmemoized `Model::where('person_id', ...)->exists()` checks (Employee, Student, Guardian) — deliberately not relations on `Person` (see below), deliberately no caching. Account type remains fully derived, never a stored column on `User`, exactly as Sprint 2.2 scoped it to become once these three models existed.

**Why direct queries instead of `Person::employee()`/`student()`/`guardian()` relations:** `AccountTypeResolver` is currently the *only* consumer of "does this Person hold this context." Adding relations to `Person` would touch a second file for a benefit with no current second consumer, and would make `Person` aware of three sibling modules it otherwise knows nothing about. The threshold for promoting to real relations: the moment a *second* independently-motivated consumer needs the same check — most likely Identity Maintenance's own Merge validation (Phase 3), which will need the identical "does the other Person already hold one of these" query — matching this project's "promotion, not prediction" rule (Addendum B1) precisely. Today there is exactly one consumer; the day a second appears, promoting is no longer speculative.

## `employee_branches` was deferred to Phase 6, not built as a shell

The execution plan's original Step 4 was reviewed for readiness before implementation and found to have no real consumer in the current architecture — a genuine YAGNI risk, not a hypothetical one. Branch membership's true owner is Employment (ADR-0005); building a transitional persistence model before its owning aggregate exists was judged to conflict with "promotion, not prediction" (Addendum B1) more than it would have saved Phase 6 any real effort, since Phase 6 would need to touch the table again regardless (adding an `employment_id` FK). Multi-branch employment remains a confirmed, real requirement — its implementation now correctly waits for the sprint where the complete domain (Employment, Position, Salary, branch membership) is built as one coherent unit.

## What Sprint 2.4 deliberately does not build

No `student_number`/`employee_number` (pending the still-open numbering-scheme decision). No `current_enrollment_id` (Phase 4, alongside Enrollment). No `employee_branches`/branch membership (Phase 6, alongside Employment). No `guardian_student`, relationship types, custody/pickup fields, notification defaults, billing responsibility, or portal access (Sprint 2.5+/Phase 7). No creation workflow for Student (Applicant→Student conversion is Phase 4) or Employee (hiring is Phase 6) — both models exist today only as schema and class, exercised solely through factories in tests.
