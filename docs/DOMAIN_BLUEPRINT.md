# AlphaSchool ERP — Enterprise Domain Blueprint

**Status:** Phase 1 (Core Architecture) complete. This is the official architectural reference for the development team, produced before the first migration is written. It consolidates every architecture decision made during the Core Architecture design phase. It does not contain code, migrations, or implementation details — those are deliberately out of scope for this document.

**Companion document:** `docs/ADMIN_PLATFORM.md` is the equally official, equally frozen reference for the React Admin application (Workspace-based navigation, dashboards, widgets, search, notifications). It governs Admin UX/Platform decisions the same way this document governs domain architecture — the two are deliberately kept separate in scope, not merged.

**Commercial model (load-bearing assumption for everything below):** dedicated instance per customer school. Every "global" concept (Users, Roles, Permissions, Settings) is global *within one customer's deployment*, not shared across customers. If this ever changes to a shared multi-tenant model, treat it as a full re-architecture trigger, not an incremental change.

---

## 1. Core Modules

**Foundation layer** — depended upon by everything; depends on nothing else in the system:

| Module | Responsibility |
|---|---|
| **Core** | Shared kernel: the temporal/Assignment pattern, Number Generator, Approval Engine, Audit Engine, Duplicate-Detection service, shared value objects (Money, DateRange, PersonName), event-dispatch infrastructure and observability. |
| **Identity** | User accounts (authentication only), Roles, Permissions, Permission Groups, branch-scoped Team assignment, Super Admin bypass, authentication/step-up-auth policy. |
| **People** | Person (identity substrate), and the context aggregates built on it: Employee, Student, Guardian. Owns contacts, addresses, identity documents, and the Family relationship layer (guardian↔student links, person-to-person relationships, households). |
| **Identity Maintenance** | *(Added 2026-07-01, see Addendum C)* Person Merge, Duplicate Resolution, Identity Correction (policy/approval layer), Identity Recovery, Person Anonymization. Orchestrates across every Domain module via standard contracts (`ReassignsIdentityReferences`, `RedactsPersonalData`) rather than direct table access — never owns day-to-day Person data, only the rare, high-stakes operations that restructure or prune the identity graph. |
| **Media** | File storage architecture: disk tiers, collections, path generation, conversions, private-document serving. |
| **Settings** | Global and branch-scoped configuration, including effective-dated settings for values that feed calculations. |
| **Notifications** | Templated, bilingual, multi-channel notification delivery and preference management. |

**Domain layer** — each owns its own tables; may depend on Foundation and on People/Identity; must never depend on sibling Domain modules directly:

| Module | Responsibility |
|---|---|
| **Admissions** | Applicant lifecycle: submission, review, testing, decision, fee calculation, payment, conversion to Student. |
| **Academic** | Academic Year, Enrollment, Grade Levels, Sections, Subjects, Timetables, Attendance, Grades, Homeroom/Subject Teacher assignments. |
| **Finance** | Invoices, Journals, Cashboxes, Fee Plans, consumption of Billing Groups for sibling discounts, payment processing. |
| **HR** | Employee lifecycle: hiring, Position history, Salary history, branch membership, leave, retirement/resignation. |
| **Inventory** | Stock, warehouses, procurement-adjacent stock transactions. |
| **Library** | Book catalog, loans, membership. |
| **Transportation** | Routes, stops, vehicle/driver assignment, student transportation assignment. |
| **LMS** | Learning content, course materials, videos. *(Note: an "assignment" in LMS means homework/coursework — a different concept from the Assignment Engine pattern in §6/§13. Keep this naming distinction explicit in code and documentation to avoid confusion.)* |
| **Reporting** | Cross-module reporting/export, consistently branch- and academic-year-scoped, permission-aware. |

**Added 2026-07-12, see Addendum E:**

| Module | Layer | Responsibility |
|---|---|---|
| **Administration Platform** | Foundation | Settings resolution, Custom Field governance, generic Favorites/Tags/Notes, Audit console + Data-Classification-targeted retention policy, format-agnostic Import/Export, Module Licensing evaluation, Data Classification declaration/enforcement. Administers; never re-implements. Not to be confused with `docs/ADMIN_PLATFORM.md`'s frontend Workspace UX architecture — same name, different bounded context, see Addendum E. |
| **Communications** | Domain | Deliberately thin aggregator for audience-broad, cross-module messaging (Broadcast, ScheduledMessage, Campaign) — mirrors the Finance aggregator exception. Never renders or delivers; always calls Notifications. |

**Named by the sponsor as examples, not yet designed — listed here for completeness, not as agreed architecture:**

| Module | Provisional responsibility (unconfirmed) |
|---|---|
| **Maintenance** | Facility/equipment maintenance requests — likely a Workflow Engine consumer (request → assignment → resolution), analogous to Leave Requests. Needs its own design session. |
| **CRM** | Prospective-student/lead management upstream of Admissions (marketing funnel before someone becomes an Applicant). Needs its own design session — including how/whether it shares identity with Admissions' Applicant concept. |

---

## 2. Domain Boundaries

- **Foundation modules** may be depended on by anyone; they depend on nothing else in the system.
- **Domain modules** may depend on Foundation and on People/Identity, but **must never depend on each other directly** — no raw querying of another Domain module's tables.
- Cross-domain interaction happens only through **domain events** (queued, for "this should also happen as a consequence") or a module's **explicit public service/contract** (for genuine synchronous reads).
- **Sibling Domain modules with no legitimate direct relationship** — communicating directly is a boundary violation: Library ↔ Transport, Inventory ↔ HR, LMS ↔ Finance, and any other Domain-to-Domain pair not explicitly justified below.
- **Finance is the deliberate aggregator exception** — it legitimately needs data from many modules (tuition from Academic, fines from Library, fees from Transport) to build invoices, but must still go through each module's public service or a shared `Billable` contract, never raw table access. This is what lets a new billable module be added later without touching Finance's core logic.
- **Family sits inside People**, not as its own Domain module — Admissions and Finance consume guardian/relationship data only through People's public service, never by querying `guardian_student` or `person_relationships` directly.
- This discipline is a **convention, not a compiler guarantee** — see §16/§17 for the recommendation to enforce it via architecture tests or code review before it can erode under deadline pressure.

---

## 3. Aggregate Roots

| Aggregate Root | Why it's a root |
|---|---|
| **Person** | The stable identity substrate every context aggregate references by ID. Owns name, DOB, gender, nationality, photo, plus child entities (identity documents, contacts, addresses). Nothing outside Person mutates its internals directly. |
| **User** | Authentication is its own bounded, security-critical concern. References Person by a single one-way FK; never gains direct FKs to context aggregates. |
| **Applicant** | Has its own status machine (submitted → … → converted/withdrawn/expired) independent of Student — must never be conflated with Student, since an accepted-but-unpaid applicant must never appear as a real student. |
| **Student** | The permanent "this person is/was ever a student" anchor. Coarse `lifecycle_status`, distinct from Enrollment's per-period status. Referenced externally by Enrollment and (in the future) Alumni features. |
| **Enrollment** | Not a Student child — Attendance, Grades, Fees, Behavior, and Report Cards all need to reference one specific enrollment period unambiguously. That heavy external cross-referencing is the signal for independent top-level identity. Owns its own status machine (promoted/repeated/transferred/withdrawn/graduated). |
| **Guardian** | A distinct relationship-context a Person can hold, independent in lifecycle from any Employee/Student context the same Person might also hold. |
| **Employee** | Same reasoning as Guardian: the permanent identity anchor, mirroring Student. **Superseded 2026-07-01 (see Addendum B2): does not directly own Position/Salary history — that responsibility moved to Employment.** |
| **Employment** | *(Added 2026-07-01, see Addendum B2)* Mirrors Enrollment's relationship to Student: one hire-to-termination period. Owns Position history, Salary history, and branch membership for that period — needed because an employee can resign and be rehired years later, and tenure/severance/benefits calculations depend on a specific employment period, not lifetime-cumulative time. |
| **Branch** | Owns only its own facts (active status, settings-override scope, optional parent branch). Other aggregates reference it; it never owns them. |
| **Academic Year** | Its own lifecycle, specifically a `closed` state enforced at the policy layer — not merely a label referenced by other tables. |
| **Household / Billing Group** | Explicitly administrator-curated membership with its own lifecycle, referenced by Finance; deliberately decoupled from the Person-relationship graph. |
| **Invoice** | Owns its line items as true children (no independent identity outside the invoice). Enforces total = sum of lines and immutability after posting. |
| **Journal** | Owns its postings. Enforces debits = credits as a hard transactional invariant — never allowed to exist unbalanced. |
| **Inventory Transaction** | Owns its transaction lines. Enforces quantity/valuation invariants at the point of the transaction. |
| **Media** (each file) | Its own lifecycle (soft-delete, versioning, conversions) even though it's polymorphically attached to other aggregates. |

**Explicitly NOT an aggregate root: Family.** A single Family aggregate breaks on divorced-parents/blended-family cases (one child would need to belong to two Family instances at once, violating "one consistency boundary per aggregate"). Family is a relationship layer, not an owning container — see §11.

**Added 2026-07-01 (Addendum C): `MergeRequest` and `AnonymizationRequest`** — owned by the new Identity Maintenance module, not by Person. Roots in their own right because they have their own workflow/approval lifecycle distinct from Person's, and because a Merge's log of reassigned references (for reversal) has no meaning nested inside either the losing or winning Person record.

---

## 4. Child Entities

| Aggregate | Owned child entities |
|---|---|
| Person | Identity documents (`person_identity_documents`), Contacts, Addresses |
| Student | Status history (`student_status_history`) |
| Enrollment | Section-assignment history, Suspension records |
| Employment | Position history (`employee_position_history`), Salary history (`employee_salary_history`); branch membership (`employee_branches`) is a jointly-relevant relationship record maintained from Employment's side. *(Moved from Employee — see Addendum B2.)* |
| Applicant | Admission assessments (test/interview records) |
| Invoice | Invoice lines |
| Journal | Journal postings |
| Inventory Transaction | Transaction lines |
| Media | Generated conversions |

**Not owned by either side — first-class relationship records, not children**: `guardian_student` (jointly relevant to Guardian and Student, carries its own verification/authorization state — see §11).

---

## 5. Value Objects

- **PersonName** — bilingual (AR/EN) structured parts with display/formatting logic in one place, not ad hoc concatenation.
- **Money** — amount + currency with consistent rounding rules, shared by Salary, Fees, Invoices.
- **Address** — lines, city, country, type, optional lat/lng (for future Transportation route planning).
- **Phone / Contact** — type, value, verification status (verification status matters directly for step-up authentication, §8).
- **Email** — validated as its own concept within Contact.
- **DateRange** — the `effective_from`/`effective_until` pair with centralized validation (no overlaps, valid ordering) — implemented once, used by every temporal entity.
- **Identity Document Reference** — `document_type` + `issuing_country` + `number` as a composite value underlying `person_identity_documents`, deliberately not a single flat string.
- **ReasonCode** — a structured, lookup-backed reason shared by every temporal/effective-dated entity, so "why did this end" is reportable, not free text.

---

## 6. Shared Patterns

- **Temporal / Effective-Dating Pattern** — the shared column convention (`effective_from`, `effective_until`, `status`, `reason_code`, `assigned_by`, `ended_by`) used identically across every historized entity.
- **Assignment Pattern** — a shared Core trait/contract providing open/close/replace/`asOf(date)` behavior, adopted by each module's own properly-typed table (not one shared polymorphic table).
- **Approval Pattern** — a shallow, genuinely generic engine using a polymorphic reference, appropriate specifically because its responsibility doesn't need domain-specific richness.
- **Workflow Pattern** — a configurable state/step engine for processes whose shape plausibly varies per customer school; not used for universal, domain-defined lifecycles (like Enrollment's).
- **Media Pattern** — Collections (semantic grouping) + tiered Disks (access level) + custom Path Generator (physical layout), each solving a different axis.
- **Audit Pattern** — two depths (cheap universal who/when; full diff-logging reserved for sensitive entities) plus an Audit Engine unifying Activitylog's forensic trail with the temporal tables' functional history into one timeline per entity.
- **Number Generator Pattern** — one centralized service and sequence table; transactional/gapless generation reserved for legally-sequential financial numbers.
- **Notification Pattern** — templated, bilingual, multi-channel, with delivery tracking and retry.
- **Versioning Pattern** — never overwrite a fact with real historical weight; append a new row (identity documents, Enrollment, Media) instead.
- **Duplicate-Detection Pattern** — fuzzy matching plus human-in-the-loop review, built for Person, reusable wherever else entity resolution matters later (e.g. Vendor records).
- **Module-Boundary / Event Pattern** — queued events for cross-module side effects; direct synchronous service calls only for same-transaction invariants.

---

## 7. Historical Data Rules

**Never overwritten (a new row is created instead):** identity documents, Enrollment (every field), Salary, Position, `guardian_student` relationship changes, Media files, Settings values that feed calculations.

**Effective-dated (closed + new row opened on change):** employee branch membership, teacher/committee/route assignments, Fee Plan versions, Student `lifecycle_status`.

**Append-only (a growing log, never a "current value" being replaced):** Activitylog entries, medical event/visit records, grade/attendance correction trails, approval decision trails, domain event logs.

**Immutable after finalization (a different mechanism, same goal):** Invoice lines once posted, Journal postings once posted.

**Derived, never stored:** the Family-tree/household view, a student's academic transcript, "current branch" (read through `current_enrollment_id`).

---

## 8. Identity Model

`User` (authentication only: username/email/phone/password/status/last_login_at) holds a single one-way FK to `Person`. `Person` owns identity (bilingual name, DOB, gender, nationality, photo) plus identity documents, contacts, and addresses. Context aggregates — `Employee`, `Student`, `Guardian`, `Applicant` — each reference `Person` by ID; a single Person can hold multiple contexts simultaneously (e.g. an Employee who is also a Guardian).

**Account Type is not stored as an enum** — it's derived from which context rows a Person has (an Employee row implies Admin Panel access; a Student row implies Student Portal access; a Guardian row implies Parent Portal access). **Super Admin is a `Gate::before` bypass** keyed off an account flag, entirely outside the Role system, so it automatically covers every branch without needing per-branch maintenance.

**Roles** (Spatie, Employee-only) are globally defined but branch-scoped in assignment via Spatie Teams (`team_foreign_key = branch_id`). **Permissions** are code/seeder-defined, grouped by translatable **Permission Groups**, and never granted directly to a user — always through a role. **Branch Access** for an employee is derived from the distinct branches (teams) they hold a role in — not from branch membership alone, since membership without any role isn't meaningful access.

**Authentication** is Sanctum-based. Plain login is not sufficient authorization for sensitive actions (registering a new child, changing payment details) — those require **step-up authentication** (OTP to an already-verified contact channel). 2FA policy for Employee Finance/HR roles remains an open decision (§16).

---

## 9. Student Lifecycle

```
Person (created/matched via duplicate detection)
   → Applicant (submitted; own aggregate, scoped to branch + academic year + grade)
      → under_review → tested → accepted/rejected
      → (if accepted) RegistrationFeeCalculated (Finance invoice, via public service)
      → payment_pending → paid
      → [synchronous] ConvertApplicantToStudentAction
   → Student + first Enrollment created together
      → Promotion: close Enrollment, open next year's at the next grade
      → Repeat: close Enrollment, open a new one at the SAME grade, new academic year
      → Branch Transfer: close Enrollment, open a new one at the destination branch
      → School Transfer Out / Withdrawal: close Enrollment, no new one opens; lifecycle_status = withdrawn
      → School Transfer In: normal Admissions flow + external_academic_records reference data
      → Suspension: a sub-status on the SAME Enrollment, not a new one
      → Graduation: event + terminal Enrollment status, NOT a new aggregate; lifecycle_status = alumni
   → Alumni (lifecycle_status only — same Student, same Enrollment history)
      → Return: a new Enrollment appended to the SAME Student (found via duplicate detection),
                same student_number retained
```

Person and Student are never re-created for a returning individual — only a new Enrollment is appended to the existing chain.

---

## 10. Employee Lifecycle

**Updated 2026-07-01 (see Addendum B2): Employment introduced as its own concept, mirroring Enrollment's relationship to Student.**

```
Hiring: Person created/matched → Employee aggregate created (permanent identity anchor)
   → Employment period opened (hire date)
   → initial employee_branches membership + initial Position (employee_position_history),
     both scoped to this Employment period
Assignment: via the Assignment pattern (Homeroom Teacher, Bus Driver, Committee Member, etc.),
   each in its own module-owned, effective-dated table
Promotion: new employee_position_history row within the current Employment — independent
   of any Role change (Position ≠ Role, see §15)
Department Change: a Position/organizational-unit change, historized the same way
Branch Change: new employee_branches row (dates) within the current Employment, old one closed;
   may separately trigger Team-scoped role reassignment
Leave: a temporary sub-status (or a Workflow-Engine-driven Leave Request), not a new
   Employment period
Retirement / Resignation: close the current Employment (termination date, exit reason);
   Employee lifecycle_status → inactive/terminated;
   Person/Employee/full Employment history retained permanently, never deleted
Rehire (years later): a NEW Employment period opened on the SAME Employee/Person,
   chained to the previous one — exactly as a returning Student gets a new Enrollment
   chained to their prior one, never a new Employee record
```

Three historized tracks run in parallel *within one Employment period*, not flatly under Employee: Position history, Salary history, and branch membership. An employee who later becomes a Guardian, or who resigns and rejoins years later, is handled by the same multi-context / duplicate-detection mechanisms as Student.

---

## 11. Family Architecture

- **Guardian Relationships** (`guardian_student`) — the safety-critical join between the existing Guardian and Student aggregates. Carries `relationship_type`, `is_primary_contact`, `is_pickup_authorized`, `custody_restriction_notes`, `verified_by`/`verified_at`, and effective dates. This is where custody/pickup/legal-restriction state actually lives.
- **Person Relationships** (`person_relationships`) — a generic, informational graph at the Person level (sibling, grandfather, uncle, and similar). `relationship_type` is a translatable lookup table, not a PHP enum — English can't represent the paternal/maternal kinship distinctions Arabic requires (عم vs خال, جد لأب vs جد لأم).
- **Billing Groups / Households** — a thin, explicitly administrator-curated grouping consumed by Finance for sibling discounts, deliberately decoupled from the relationship graph (a graph-derived "sibling" edge from a remarriage shouldn't automatically create a billing unit).
- **Verification** — a root of trust is established once, at a guardian's first child's application (real identity-document check, registrar-confirmed), and reused for every subsequent application by that same guardian. Session/account-takeover risk is handled separately via step-up authentication (§8). Anomalies (contradicting family data, unverified accounts claiming multiple children) route to manual registrar review via the same fuzzy-matching mechanism used for duplicate detection.
- **No Family aggregate root exists.** "Family" as shown in the UI is a derived read — a traversal of `guardian_student` + `person_relationships` — never a stored row.

---

## 12. Media Architecture

- **Collections** — fixed, documented per-model semantic groupings (a Student's `photo`, `documents`, `medical_reports`), never free text.
- **Disks** — exactly 3 access tiers: `public` (CDN-fronted), `private` (never publicly reachable), `temporary` (ephemeral, lifecycle-purged) — not one disk per category. Disks represent physical storage backend and serving mechanism only, not fine-grained authorization.
- **Sensitivity classification** *(added 2026-07-01, see Addendum B3)* — within `private`, collections declare a sensitivity level (`standard` or `high`). High-sensitivity collections (medical reports, court documents, identity documents, psychological reports) get mandatory view/download audit logging, a dedicated Policy class, and potentially longer regulatory retention — not a separate disk, since the physical storage/serving mechanism is identical either way.
- **Custom Paths** — `{tier}/{branch_id}/{model-type}/{model_id}/{collection}/{media_id}-{filename}`, colocating one entity's files and spreading writes across prefixes.
- **Visibility** — a property of the collection *definition* (`useDisk('private')`), never a per-upload toggle a user could get wrong.
- **Image Conversions** — defined per collection via reusable conversion profiles, generated by queued jobs, never synchronously on upload.
- **Private Documents** — served only through an authenticated streaming route (never raw signed URLs, so a revoked role instantly revokes access); view/download of sensitive collections is itself logged.
- **Public Files** — CDN-fronted; cache invalidation is solved implicitly because files are never overwritten in place (a new upload is a new ULID).
- **Versioning** — version-sensitive collections (contracts) accumulate every upload rather than overwriting a single slot; deletion is soft-delete plus a scheduled purge, not Spatie's default hard delete.
- **Audit** — the Media model is extended with Activitylog's `LogsActivity` trait and unified into the Audit Engine's timeline, rather than a parallel audit mechanism.

---

## 13. Core Engines

| Engine | Role |
|---|---|
| **Assignment** | Shared temporal-assignment behavior (open/close/replace/`asOf`), adopted by module-owned tables — not a shared table. |
| **Approval** | Generic, shallow, polymorphic — tracks who must approve what and records decisions, without needing domain-specific richness. |
| **Workflow** | Configurable multi-step process engine, opt-in for processes whose shape plausibly varies per customer school. |
| **Notification** | Bilingual template rendering, channel/preference selection, delivery tracking and retry. |
| **Audit** | Unifies Activitylog's forensic trail with the temporal tables' functional history into one timeline per entity. |
| **Number Generator** | Centralized sequence service; transactional/gapless mode for legally-sequential financial numbers, lighter mode for reference codes (student numbers, QR/certificate numbers). |
| **Media** | Already covers what might otherwise be called a "Document Engine" — no separate engine needed. |
| **Duplicate Detection** | Fuzzy matching + human-in-the-loop review, generalized beyond Person for future entity-resolution needs. |
| **Reporting / Export** | Consistent branch/academic-year scoping and permission-aware data access, built once rather than per report. |

---

## 14. Domain Events

`ApplicationSubmitted`, `AdmissionTestScheduled`, `AdmissionDecisionMade`, `RegistrationFeeCalculated`, `ApplicationPaymentCompleted`, `StudentEnrolled`, `StudentPromoted`, `StudentRepeated`, `StudentTransferred`, `StudentWithdrawn`, `StudentGraduated`, `StudentSuspended`, `StudentReenrolled`, `EmployeeHired`, `EmployeePositionChanged`, `EmployeeSalaryChanged`, `EmployeeAssigned` (generic — e.g. `HomeroomTeacherAssigned`), `EmployeeBranchChanged`, `EmployeeTerminated`, `GuardianVerified`, `AcademicYearClosed`, `AcademicYearReopened`, `ApprovalRequested`, `ApprovalDecided`, `InvoiceIssued`, `InvoiceVoided`, `MediaArchived` (graduation-triggered storage-tier transition).

---

## 15. Architecture Principles

- Never overwrite history — version or append instead.
- Authentication is not Identity: User ≠ Person.
- Account Type is not Role: classification ≠ permission.
- Role is not Position is not Assignment — three orthogonal axes, never implemented in terms of each other.
- Assignments, memberships, and relationships are temporal by default.
- A current-state pointer is a cache, never a source of truth.
- Finance must never depend on Family relationships directly — only via Billing Groups or People's public service.
- Domain modules never query each other's tables directly — events or public services only.
- Workflow is configurable where its shape must vary per customer; hardcoded where it's universal.
- Polymorphism is for shallow, generic coordination concerns (Approval, Notification) — never for domain-rich concerns (Assignment).
- Every customer-specific need must be solvable through configuration, never a code fork.
- Rules that feed calculations must be versioned as rigorously as the data they calculate from.
- Person is the substrate; every context is a lens on a Person, never a copy of one.
- An aggregate references other aggregates by ID; it only owns entities with no independent existence outside it.
- Conventions without enforcement decay under deadline pressure — name the enforcement mechanism, not just the rule.

---

## 16. Open Decisions

- 2FA policy for Employee accounts, especially Finance/HR roles.
- Student/Employee numbering: globally unique across branches, or branch-prefixed.
- School-level Team/permission scoping mechanism if a second school ever materializes (Spatie Teams supports only one scoping dimension — this may require moving off Spatie Teams entirely, not just adding a column).
- Database-level retention/purge policy for `activity_log` and soft-deleted rows over a 15-year horizon.
- **The enforcement mechanism for the three convention-dependent risks** (module boundaries, custom-field governance, temporal/effective-dating discipline) — flagged as the single most important item to resolve before the first migration.
- Upgrade/migration story across many independent customer databases as the product scales.
- Impersonation ("login as") design, beyond the audit-marker placeholder.
- Whether shared-custody situations require two-guardian corroboration for an application.
- Maintenance and CRM — named as examples in this review but never designed; need their own dedicated sessions, the way Family did.
- Local accounting-regulation confirmation needed before finalizing Finance's gapless-numbering sequence scope.

---

## 17. Readiness Assessment

**Ready:** Identity, People (Person/Employee/Student/Guardian/Applicant), Family relationships, Student/Academic lifecycle (Enrollment), Media architecture, and the cross-cutting patterns/engines (§6, §13) — all have been through multiple rounds of challenge and revision and are internally consistent with each other.

**Not yet designed at all** — referenced only as consumers or examples, never actually designed: Finance's internals beyond aggregate-boundary level (Invoice/Journal structure, Fee Plans), HR's internals beyond Position/Salary/Assignment, Inventory, Library, Transportation, LMS, Reporting, Maintenance, CRM.

**Recommended implementation order:**

1. **Core primitives** — the shared temporal trait *and its Pest architecture tests* (built together, not sequentially — see Addendum §A3), Number Generator, minimal Organization/licensing table, base value objects (Money, DateRange, PersonName), Media architecture skeleton.
2. **Identity** — User, Person, Roles/Permissions/Permission Groups, Branches with Teams enabled.
3. **People** — Employee, Guardian, Student, contacts/addresses/identity-documents, Family relationships.
4. **Academic Year + Grade Level (catalog only)** — the lightweight reference data Admissions genuinely depends on; NOT the full Enrollment machinery (see Addendum §A1).
5. **Admissions** — built through to "paid" against just Academic Year/Grade Level; validates the Workflow Engine against its first real consumer.
6. **Enrollment** — completed in parallel with or immediately after Admissions; the conversion action is the one integration point needing both.
7. **Notification + Approval engines** — needed once Admissions and HR-adjacent workflows exist.
8. **Academic (Sections/Attendance/Grades/Teacher Assignments), HR (Position/Salary/branch detail), Finance (Invoices/Fee Plans/Billing Policies)** — in parallel or by business priority; each consumes the already-established patterns rather than inventing new ones.
9. **Inventory, Library, Transportation, LMS, Reporting** — standard Domain modules following the established boundary/event/media/temporal patterns; lower risk since the hard identity/temporal/module-boundary decisions are already made.
10. **Maintenance, CRM** — require their own dedicated design sessions before implementation, the same treatment Family received.

---

## Addendum: Validation Pass (pre-implementation review)

A final challenge-and-validate pass against this blueprint, before the first migration. None of it reopens a settled decision — it resolves an open item and tightens several sections.

**A1. Admissions/Academic ordering corrected** — the original order bundled "Academic Year + Enrollment" as one prerequisite to Admissions. Only Academic Year and Grade Level (lightweight catalog entities) are true prerequisites; the full Enrollment aggregate is only touched at Admissions' final conversion step. Order updated above. The conversion action is a cross-module orchestration (calls People's create-Student and Academic's open-Enrollment through their public services), not a pure Admissions-internal operation.

**A2. Organization added to Core** — scoped narrowly as vendor/licensing identity (legal/display name, module licensing flags, support-contract metadata), NOT as a business hierarchy layer above School. Deliberately kept separate in purpose from School (a domain/academic concept for a future multi-school scenario). `schools.organization_id` becomes a real FK. New aggregate root, added to §3.

**A3. Temporal-pattern enforcement mechanism resolved (closes the §16 item)** — prioritized, not a flat list of options: (1) the shared trait, designed to be less work than a shortcut; (2) Pest architecture tests (already in the stack — `arch()->expect(...)`) enforcing module-boundary and temporal-pattern rules at CI time; (3) PHPStan/Larastan, not yet in the stack, recommended specifically for module-boundary static analysis; (4) code review as the last-resort human backstop, not the primary mechanism. Build the trait and its architecture tests together, before any Domain module migration exists.

**A4. Enrollment/curriculum snapshot rule added to Historical Data Rules** — cosmetic catalog renames (a Grade Level's display name) don't require Enrollment to reference immutable curriculum versions; a report card takes a structured snapshot (grade/subject/teacher names as they were) at the moment it's *finalized/published*, extending the existing "immutable after finalization" pattern already used for posted Invoices. Interpretive rule changes (a Grading Scale's definition) are different in kind — those must be genuinely versioned as their own effective-dated entity, since a stored grade is meaningless without knowing which scale produced it.

**A5. Billing Policy pattern added to §6/§13** — no single "Billing Policy Version" entity (would repeat the Family God-Object mistake). Instead: several small, independently effective-dated policy entities (Sibling Discount, Employee Discount, Scholarship, Late Fee, Installment), all owned by **Finance** even where they read from another module (Sibling Discount reads Household membership from People's public service). Scholarships route through the Approval Engine as individually-decided grants. A lightweight "applied policy" record is captured on the Invoice/line at posting time, immutable, so a historical invoice always shows which rates actually applied.

**A6. Read Model guidance differentiated, not blanket** — Family Tree and Current Enrollment are already solved (derived query; pointer). Student/Guardian Dashboards get a query-time aggregating service (start unmaterialized, promote only if profiling proves it necessary). Financial Summary reports use the already-decided cached/materialized pattern with explicit TTL — aggregate financial computation over large historical data genuinely justifies it.

**A7. Audit Timeline confirmed as an exposed feature**, not just internal capability — one per entity, merging events from every module that touched it. New convention: every domain event must carry the primary entity/entities it concerns as part of its payload, so the timeline can index by subject without per-event-type special-casing. Explicit guardrail: the Audit timeline is read-only/display-only — it must never become a de facto integration API between modules, which would quietly defeat the module-boundary rule.

**A8. Frozen decisions identified** (would require real data migration, not just new code, to reverse): Person as the identity substrate; Enrollment as separate from Student; the dedicated-instance-per-customer commercial model; Branch as the Spatie Teams scoping unit; Applicant separate from Student; Media's physical path scheme and three-tier disk model. Explicitly not frozen: Roles/Permissions, Assignment types, Fee Plan/Billing Policy versions, which Domain modules exist, Workflow Engine configuration.

**A9. Additional risks identified beyond §16**: the Audit Engine (A7) becoming a backdoor coupling point if read as a shortcut integration path instead of going through proper services; Enrollment-chain query performance at scale needing deliberate composite indexing (`student_id, status` and `branch_id, academic_year_id, status`), not assumed-safe; cross-module dashboard fan-out latency (§6) needing parallelization or materialization if proven slow; the Enrollment Snapshot rule (A4) needing to live under the *same* enforcement mechanism as A3, not a separately-remembered rule; Core-engine governance once multiple teams build on Assignment/Approval/Notification/Number-Generator/Audit simultaneously — Core needs its own ownership/review discipline distinct from ordinary module review.

**A10. Implementation approved to begin.** Core first, unchanged in principle from the original recommendation — sharpened to mean building the temporal trait and its architecture tests together, before any Domain module exists, so every subsequent module is built under the enforcement regime rather than retrofitted onto it.

---

## Addendum B: Final Pre-Implementation Validation (2026-07-01)

A second, final validation pass, prompted by concern over Core becoming a dumping ground and a few remaining boundary questions. As with Addendum A, nothing here reopens a settled decision — B2 is the one genuine structural correction; the rest formalize conventions that were previously implicit.

**B1. Core inclusion/exclusion rules established.** Three tests, applied together: (1) **Domain-agnosticism** — would this code make equal sense in an unrelated ERP (hospital, logistics)? If it only makes sense because this is a school, it does not belong in Core, regardless of how generic it feels while being written. (2) **Promotion, not prediction** — Core is populated by extracting something already built and reused by a *third* independently-owned module, never by forecasting reuse in advance. (3) **Low churn** — Core code should rarely need to change; frequent tweaks to accommodate one module's special case is a sign the concern should live in that module instead. Per category: no generic "Helpers/Utils" namespace at all (every shared concern gets a specific, named home); Traits belong in Core only for genuinely domain-agnostic cross-cutting behavior; Services belong in Core only for technical capabilities, never business decisions; Value Objects belong in Core only when they carry no ERP-specific meaning; Contracts/Interfaces (`Billable`, `Approvable`, `HasTemporalAssignment`) are a clean fit for Core, since they're exactly the tool that keeps module boundaries clean. Frontend "Shared Components" (UI library) are a separate concern from backend Core and should live in their own package.

**B2. Employment introduced as its own concept — corrects §3/§4/§10.** Position history and Salary history no longer hang directly off Employee; they belong to a new **Employment** entity (one hire-to-termination period), mirroring Enrollment's relationship to Student. Reason: an employee can resign and be rehired years later, and tenure/severance/benefits calculations depend on a specific employment period, not lifetime-cumulative time — the identical shape of problem Enrollment already solves for Student, applied to a second aggregate. A rehire opens a new Employment period chained to the previous one; Employee and Person are never recreated. Boundary restated: **Employment** = which contractual period; **Position** = what organizational title during that period; **Assignment** = what specific, time-bound responsibility layered on top (Homeroom Teacher, Committee Member) — three orthogonal axes, none implemented in terms of the others.

**B3. Media sensitivity formalized, without adding a fourth disk tier.** Disks remain exactly 3 (public/private/temporary) — they represent physical storage/serving mechanism, not fine-grained authorization. Within `private`, collections now declare a `sensitivity` level (`standard`/`high`). High-sensitivity collections (medical, court documents, identity documents, psychological reports) get mandatory view/download audit logging, a dedicated Policy class per collection, and potentially longer regulatory retention — formalizing what was previously an ad hoc list into a real, declared classification.

**B4. Document Governance is one unified framework, not per-module lifecycles.** HR, Finance, Medical, Academic, and Library documents all configure retention period, versioning mode, and sensitivity through the same Media Architecture mechanism — modules own the *parameters* (a medical report might need 10-year retention, an HR letter 5), never the *mechanism*. This is the same resolution shape as the Assignment pattern (shared trait, module-owned tables) and the Billing Policy pattern (shared effective-dating, module-owned entities) — one architectural instinct, applied a third time.

**B5. Translation convention formalized — sharper than "visible = translatable."** The precise test: does the string's *identity* matter to program logic, or its *display value* matter to a human reader? (1) Codes/identifiers used by program logic (`Role.name`, `Permission.name`, document-type enum values) — never translated, one canonical string, regardless of visibility. (2) System vocabulary genuinely meaning the same thing in both languages (Permission Group names, Settings labels, UI copy) — Spatie Translatable. (3) Personal/proper-noun bilingual data that is transliteration, not translation (Person names) — explicit flat bilingual columns, never Translatable. Documented precisely to prevent a future developer from re-deriving the Person-name mistake from a naive "visible = translatable" reading.

**B6. Branch Ownership — formal written guideline, as requested.** Default: new entities are **global unless they represent something that physically or operationally happens at a specific location** — burden of proof is on adding `branch_id`, not on omitting it. Four shapes, not a binary:

| Shape | Examples |
|---|---|
| Direct `branch_id` column | Enrollment, Invoice, Cashbox, Attendance |
| Many-to-many membership | Employee ↔ Branch (via Employment's `employee_branches`) |
| Global catalog + branch-availability join | Subjects, Grade Levels |
| Global default + branch-level override | Settings; likely Fee Plan rates and Academic Year calendar detail once Finance/Academic are designed |

Standing rule: **Person, User, Student, Employee, Guardian are never directly branch-scoped** — branch relevance always flows through Enrollment or Employment's `employee_branches`, never as a column on the identity/context aggregate itself.

**B7. API versioning: `/api/v1` from day one, confirmed.** Version at the URL path (not a request header — simpler for Scramble's generated docs and for future debugging) and at the whole-API level (not per-resource — added complexity with no evidence it's needed here).

**B8. Architecture governance model established**, layered cheapest/most-reliable first: (1) **Prevention** — traits/scaffolding making the correct pattern the path of least resistance. (2) **Automated CI gates** — Pest architecture tests for structural rules, **`deptrac`** specifically for the module-dependency graph (Domain modules must not import each other), Larastan for type-level/custom static rules. (3) **Process gate — Design Review**: any change touching a frozen decision (A8), adding to Core (B1), or crossing a module boundary in a new way requires a written ADR *before* implementation. (4) **Documentation of record** — recommend converting this entire multi-session conversation into individual numbered ADR files (`docs/adr/0001-person-as-identity-substrate.md`, etc.) with a standard Context/Decision/Consequences/Alternatives-Considered template. The Blueprint and ADRs are kept distinct and serve different purposes: Blueprint = what's true now; ADRs = why we got here and what was rejected, which a future architect needs before proposing to change something frozen. (5) **Code review checklist** — last, most fallible layer, for judgment calls the automated layers can't catch.

**B9. Two genuine gaps identified, not previously covered:**
- **Eventual-consistency reconciliation** — the queued, decoupled event architecture needs a dead-letter/reconciliation mechanism for when a listener permanently fails after exhausting retries (e.g., Finance's `StudentEnrolled` listener fails silently and no invoice is ever created, with nothing surfacing that fact). Needs deciding before Phase 2 modules lean on the event architecture heavily.
- **Listener idempotency** — every queued listener that mutates data must be idempotent (check for an existing effect before creating a new one), stated as a standing rule alongside the event architecture itself, not left for each future listener author to rediscover independently.
- Additionally: the temporal/historical-correctness guarantee (§7, A4) has no corresponding *testing discipline* yet — recommend a distinct test category (beyond the structural architecture tests in B8) that simulates "generate a report as of N years ago, after later changes were made" and asserts the historical answer still holds.

**B10. Final approval reaffirmed.** Nothing in this validation pass reopened a settled decision. Core-first sequencing stands, now additionally informed by B1's inclusion rules from the very first commit.

---

## Addendum C: Identity Maintenance (2026-07-01)

Closes the one gap identified in the final pre-implementation challenge (a missing answer to the conflict between "never overwrite history" and the legal right to erasure). **This addendum retracts and replaces the "standalone Erasure Engine in Core" idea floated when the gap was first raised** — that framing was inconsistent with Core's own domain-agnosticism rule (B1): anonymizing a Person requires deep, specific knowledge of Enrollment/Employment/Invoices/Media, which is not domain-agnostic, so it cannot live in Core.

**C1. Identity Maintenance is a new Foundation-tier module**, added to §1 above — alongside People, not folded into it and not placed in Core. Responsible for: Person Merge, Duplicate Resolution, Identity Correction, Identity Recovery, Person Anonymization. Justified by three tests already used throughout this project: (a) **cohesion** — all five manage the integrity of the identity graph itself, a genuinely distinct responsibility from People's day-to-day content management; (b) **risk profile** — all five are rare, high-stakes, hard-to-reverse, and need governance (elevated permission, Approval-Engine gating) categorically different from routine People operations; (c) **cross-module reach** — a real Merge must reassign references across Academic, HR, Finance, and Media, an orchestration responsibility significant enough to name and bound explicitly rather than leave implicit inside People.

**C2. The five capabilities are not uniform in shape:**
- **Duplicate Detection** (the fuzzy-matching algorithm) stays a generic Core service, unchanged — it's genuinely domain-agnostic (reusable for Vendor de-duplication in Inventory, as already noted). **Duplicate Resolution** (the workflow of reviewing a flagged match and deciding merge vs. dismiss) belongs to Identity Maintenance, since it has real domain consequences.
- **Identity Correction** mostly isn't a new table — it's a policy/approval layer over the mechanism already decided (edit in place, Activitylog diff, required reason, elevated permission). Adds tiering: minor corrections stay lightweight; substantive changes (nationality, DOB) route through Approval-Engine gating like Merge.
- **Merge must be designed reversible-by-construction** — never delete the losing Person; mark `merged_into: <winning_person_id>`, reassign references, retain a log sufficient to reverse it. This is what makes **Identity Recovery** achievable for Merge/Correction — it's a direct consequence of how Merge is built, not a separate bolted-on feature.
- **Recovery does not apply to Anonymization the same way.** Merge/Correction are reversible operational mistakes; a legally-mandated anonymization is deliberately irreversible by design once executed — reversing it would itself be a compliance violation. "Recovery" for Anonymization only applies pre-execution (cancelling a pending request during its approval workflow).

**C3. Cross-module orchestration via standard contracts, not direct table access.** Every Domain module holding a Person reference implements two small interfaces: `ReassignsIdentityReferences` (`reassignPerson(oldId, newId)`) and `RedactsPersonalData` (`anonymizePerson(personId)`). Identity Maintenance calls these across every registered module to execute a Merge or Anonymization, without needing direct knowledge of any module's schema — preserving the module-boundary rule rather than requiring an exception to it. **New governance rule, added to the Design Review checklist (B8):** every new Domain module must implement both contracts as part of its initial build, the same way it's already expected to respect branch-ownership (B6) and the temporal pattern (A3) from day one.

**C4. New aggregates**: `MergeRequest` (losing/winning person, status, requested/approved/executed/reversed timestamps, a log of reassigned references) and `AnonymizationRequest` (person, legal basis, status, approver, executed timestamp, redacted-fields record). Both route through the Approval Engine and are surfaced prominently in the Audit Engine's unified timeline — more prominently than routine operations, since these are exactly the actions an administrator reviewing history most needs flagged clearly. The permanent record that an erasure occurred (who requested it, who approved it, what was redacted, when) is itself never erasable — the one exception to Anonymization's own effect.

**C5. Why this is the better long-term shape for a commercial ERP**: different customer schools sit under different jurisdictions with different correction/erasure requirements. A single, well-bounded Identity Maintenance module makes that variation configurable per deployment (which contracts are enforced, which approval chains apply) rather than scattered logic buried inside People — directly consistent with "every customer-specific need must be solvable through configuration, never a fork" (A9/B4's recurring principle, applied a fourth time).

**C6. With this decided, Phase 1 architecture is considered complete.** No further decision at the magnitude of Person-as-substrate, Enrollment/Student separation, the commercial model, or this Identity Maintenance module remains open.

**C7. Merge Preview and Dry Run** — not separate mechanisms, both fall out of the `ReassignsIdentityReferences` contract directly. Add a read-only companion method, `previewReassignment(oldId, newId): ReassignmentImpact`, alongside the executing `reassignPerson(oldId, newId)`. Dry Run is the same contract methods invoked with a `$dryRun` parameter, running full validation (see C9) without committing writes or dispatching events — implemented as a parameter on the contract, NOT as "run the real operation in a transaction and roll it back," since events dispatched mid-merge aren't transactional and wouldn't be undone by a DB rollback.

**C8. Merge Rollback — required data.** `MergeRequest` owns a `merge_reassignment_log` child (module, entity type, entity ID, field, old Person ID, new Person ID — one row per reference actually reassigned) plus a snapshot of the losing Person's own state at merge time (`lifecycle_status`, flags). Rollback is not assumed safe indefinitely: if new data has been created since the merge that assumes it happened (a new Invoice on the winning Person, a second merge stacked on top), the rollback capability must detect this and block or clearly warn, rather than silently reversing into a fresh inconsistency.

**C9. Business-rule validation on Merge/Anonymization — two owners, not one.** Structural conflicts inherent to the operation itself (both people have simultaneous active Employments, overlapping active Enrollments, two live User logins) are validated directly by Identity Maintenance — it's about the structural validity of the combined identity, not domain knowledge. Domain-specific vetoes (financial reconciliation incomplete, legal investigation open, active disciplinary case) are NOT Identity Maintenance's knowledge to own — extend the contract family with `canReassignPerson(personId)` / `canRedactPerson(personId)`, implemented by each owning module (Finance, HR, etc.), called during Dry Run. Identity Maintenance enforces "if any module objects, block" without needing to understand why.

**C10. Identity Governance — decisive tiering, not "some operations require approval."** Merge and Anonymization always require approval, with no self-approval exception even for Super Admin — the four-eyes principle applies precisely because Super Admin has the most reach to cause damage acting alone. Identity Correction is tiered: fields feeding a legal/financial/eligibility determination (DOB, nationality, national ID) require approval like Merge; cosmetic corrections stay immediate (reason + Activitylog only, no approval gate), unchanged from the original Person-versioning decision. Approval authority sits behind a dedicated "Identity Governance" Permission Group (`identity.approve-merge`, `identity.approve-anonymization`), not generic admin access.

**C11. Module contracts are optional to implement, but mandatory to declare.** A module with no Person references anywhere in its schema should not be forced to implement `ReassignsIdentityReferences`/`RedactsPersonalData` with empty bodies — but it must explicitly declare "I hold none" as part of module registration, rather than silently omitting the interfaces. This makes absence deliberate and auditable. Architecture-test requirement: scan each module's schema for columns plausibly referencing Person (`*_person_id`, `student_id`, `employee_id`, `guardian_id`) and fail CI if a module declared "none" but has one anyway — catches schema drift silently breaking Merge/Anonymization completeness.

**C12. Identity Timeline is the Audit Engine's existing per-entity timeline (A7), not a new subsystem** — applied to Person, with Identity Maintenance events tagged for filtering/visual priority over routine changes. One added rendering requirement: a "Merge Executed" entry must be drillable into the `merge_reassignment_log` (C8) so an administrator can see exactly what moved, not just that a merge happened.

---

## Final Foundation Validation (2026-07-01)

Foundation layer confirmed complete: Core, Identity (including Authorization — Roles/Permissions/Permission Groups/Teams, already fully designed; not a separate module, a naming clarification only), Identity Maintenance, People, Media, Notifications, Settings.

**One clarified item, resolved without a schema change**: calendar-system support. Confirmed direction — **Hijri calendar as a display convenience only**, alongside Gregorian, which remains the sole storage/operational calendar throughout the system (Academic Year, Enrollment, Attendance all stay Gregorian). This resolves as a Core-level date-formatting convention (compute a Hijri-equivalent from a stored Gregorian date at render time, wherever dates are shown in UI/API responses) — no new column, table, schema change, or module required. Named now so date-display code isn't built assuming Gregorian-only output later, but it does not block the freeze.

**PHASE 1 ARCHITECTURE: FROZEN.** No further architectural decision is open at the magnitude that has been the bar throughout this process (comparable to Person-as-substrate, Enrollment/Student separation, the commercial model, or Identity Maintenance). Implementation may begin, starting with Core, per the recommended order in §17 and Addendum A10/B10.

---

## Addendum D: Cross-Cutting Concerns Review (pre-Sprint-1, 2026-07-02)

A final challenge pass on six cross-cutting concerns before the first domain migration, run after Sprint 0.1 (Engineering Bootstrap) completed. Only one of the six (D4, Public IDs) rises to "must decide before Sprint 1" — the rest are real but correctly sequenced later, and are recorded here specifically so they aren't silently reinvented differently by different future modules.

**D1. Custom Fields — real requirement, wrong timing to build now.** Justified by the commercial no-fork principle (different customer schools need different Student fields without a schema migration per field). But there is no cheaper "ad hoc first version" here — the metadata-driven shape (a `CustomFieldDefinition` table: entity type, field key, translatable label, field type, validation rules, is_searchable — plus a `custom_attributes` JSON column on the consuming entity) has to be built correctly the first time, since the entire value proposition (no migration per field) must hold for the very first customer request, not just once generalized. Building this speculatively in Core before a named consumer exists would be prediction, not promotion (B1). **Decision: postpone until a real customer field request arrives** (likely attaching to Student in or after Phase 2) — build it there in the metadata-driven shape, then promote the trait/definitions pattern to Core once a second entity needs the identical capability. Searchable custom fields feed into the Search abstraction (D5) as indexed metadata; the Reporting engine must become custom-field-aware (dynamic columns per entity type) once this exists; custom field values get standard Activitylog-level audit only, never full temporal versioning, unless a field is later promoted to a real column — consistent with the original custom-fields governance rule from the enterprise architecture session.

**D2. Universal Tags — approved for Core, Sprint 1.** Unlike Custom Fields, a tag is deliberately shallow (a label with no domain-specific richness — "Gifted" needs no FK the way a Homeroom Teacher assignment needs a section reference), which places it in the same low-risk polymorphic category as Approval and Notification, not Assignment (per the polymorphism dividing line already established). Low risk of building the wrong abstraction means the promotion-vs-prediction caution doesn't apply as strongly here. Model: `tags` (translatable name, a `scope` restricting which entity type(s) it's valid for — Student/Guardian/Employee/Invoice tags are clearly different vocabularies and must not share a free-for-all pool) + a polymorphic `taggables` pivot. Not the Assignment/temporal pattern — most business tags are persistent labels, not time-bound facts; a simple `tagged_at`/`tagged_by` audit pair is sufficient, with removal going through ordinary Activitylog.

**D3. Universal Notes — approved for Core, Sprint 1.** Same shallow/generic reasoning as Tags. Two refinements beyond what was proposed: (1) **portal-visibility must be a separate, explicitly permission-gated flag** (e.g. `is_guardian_visible`), not one value in a `private/internal/public` enum — a note carelessly marked "public" that reaches a Guardian/Student portal is a real incident, the Notes equivalent of a Media sensitivity-classification failure (Addendum B3). (2) **Notes are append-only, not editable in place** — extends the existing Historical Data Rules (§7) rather than being a new decision, since a note often carries evidentiary weight ("the parent was informed of X on this date") that silent editing would undermine. Attachments create a legitimate Foundation→Foundation dependency on Media.

**D4. Public IDs — decided now, the one item on this list that cannot wait.** ULID (not UUID v4) as the external identifier: lexicographically sortable (embedded timestamp), avoiding the B-tree index fragmentation UUID v4's random distribution causes at the scale this system targets, while remaining unguessable enough to prevent IDOR-style enumeration. **Dual-ID strategy for domain aggregates**: the internal auto-increment integer stays the real primary key (cheap joins across the join-heavy Enrollment/Employment chains this architecture is built around); a separate unique-indexed `public_id` (ULID) column is used for all external API representation and route-model binding. The raw internal `id` must never appear in any API response — enforced structurally via a base `ApiResource` class that maps to `public_id`, backed by an architecture test scanning for a leaked raw `id`, the same enforcement pattern used for module boundaries and the temporal convention. **Media is the deliberate exception**: since it already uses a ULID for the physical stored filename, Media's actual primary key should simply *be* that ULID rather than adding a third identifier — Media isn't joined transitively across other tables in hot-path queries the way Person/Enrollment are, so the join-performance argument for a dual-ID scheme doesn't apply there. Rationale for deciding this now rather than later: once Person/User/Student ship with only integer IDs exposed externally, fixing it later means either a breaking API change or an awkward retrofit migration — this is the "expensive to retrofit" bar the rest of this addendum's items don't meet.

**D5. Search abstraction — reaffirmed, unchanged.** Already decided in the enterprise architecture session: build against Laravel Scout's interface from day one (even using Scout's `database` driver initially); introduce Meilisearch/OpenSearch/Elasticsearch only once there's real data volume and an observed pain point, never speculatively. Clarification added: this is distinct from the Duplicate-Detection service (fuzzy entity-resolution matching, e.g. "is this the same Person") — the two must not be conflated into one mechanism. The Scout-first *policy* is a Core-level convention worth documenting now; actual per-model adoption happens as each module is built, no dedicated Sprint 1 work required beyond writing the convention down.

**D6. Documentation structure — split now, before Finance/HR/etc. content accumulates.** Same "cheap now, expensive later" reasoning already applied repeatedly to schema decisions (Spatie Teams, `parent_branch_id`, `school_id`), applied here to documentation instead. Planned structure: `docs/architecture/` (cross-cutting content only — module boundaries, aggregate rules, shared patterns, historical data rules, architecture principles, currently Blueprint §2/3/6/7/15), `docs/foundation/` (one file per Foundation module), `docs/domain/` (one file per Domain module, created as each gets its real design session — undesigned modules get an explicit stub, not silent absence), `docs/playbooks/`, `docs/adr/`, `docs/developer/` (already correctly shaped). `DOMAIN_BLUEPRINT.md` becomes a genuine index linking out to detail files. Concrete follow-up this creates: the seven existing ADRs' `## References` links point at section numbers that will move and need updating when this split is executed. Treated as its own small piece of work, not bundled into Sprint 1's domain-code scope.

**D7. Readiness verdict.** Reviewed the remaining candidates that could plausibly rise to "decide before Sprint 1" (optimistic locking/conflict detection for ordinary concurrent edits, API rate-limiting, multi-currency, data residency) and none clear the bar — all are addable later, incrementally, without a redesign. **Public IDs (D4) is the only item requiring a decision before Sprint 1, and it is now decided.** With that resolved, the architecture is ready for Sprint 1 — Tags and Notes may be built in Core immediately alongside it; Custom Fields is deliberately deferred to its first real consumer; Search and Documentation structure are settled/planned but not blocking.

---

## Addendum E: Administration Platform, Communications, and the Notification Engine (2026-07-12)

A dedicated architecture session, run after Phase 2 (Identity & People Foundation) was frozen and released as `v0.7-people-contexts`, ahead of any implementation sprint for these three concerns — the same sequencing already used for Family's design session ahead of Sprint 2.5. Full detail in `docs/adr/0011-administration-platform-bounded-context.md`, `docs/adr/0012-communications-as-thin-aggregator-domain-module.md`, `docs/adr/0013-channel-provider-separation-for-notification-engine.md`, and `docs/developer/administration-platform-and-communications.md`. Summarized here since each introduces or substantially details a module named in §1's tables.

**E1. Administration Platform (ADR-0011)** — a new Foundation-tier bounded context absorbing Settings' existing charter plus Custom Fields, Favorites/Tags/Notes, Audit/Retention (targeting a new Data Classification concept — Identity/Financial/Academic/Operational/Audit, a closed-but-extensible enumeration, retention targets a classification by default), format-agnostic Import/Export, and Module Licensing evaluation. Explicitly not the same thing as `docs/ADMIN_PLATFORM.md`'s frontend Workspace concept — a naming coincidence, resolved by prose convention, not by renaming either document. Licensing's `OrganizationModule` (Core, Sprint 2.3) stays correct as-is until a real subscription/renewal/add-on need arrives, or until licensing accumulates multiple business-process verbs (trial/renewal/upgrade/downgrade/suspension) against the same data — whichever comes first — at which point it promotes to a full `Subscription` aggregate in Core, beside Organization.

**E2. Communications (ADR-0012)** — a new Domain module, a genuine extension of the module list in §1, not previously named anywhere in this Blueprint. Deliberately thin: owns only Broadcast/ScheduledMessage/Campaign (audience-broad, cross-module messaging), mirrors Finance's aggregator exception via a mirrored `Audienceable` contract per module, and never renders or delivers — every send goes through Notifications. Transactional, one-to-one business messages never route through Communications; they dispatch directly from their originating module to Notifications, per the existing event-driven discipline (§14).

**E3. Notification Engine Channel/Provider architecture (ADR-0013)** — details Notifications' already-named §1 charter: a Channel (Email/SMS/WhatsApp/Push/In-App) is a contract, a Provider is a vendor's swappable implementation, resolved via a Laravel Manager (the same mechanism already governing this codebase's Storage/Broadcasting config). WhatsApp carries one documented, bounded exception (vendor-dependent template-approval/session-window business rules); In-App is not vendor-swappable (persistence is Engine-owned; only its optional real-time layer rides the existing Broadcasting driver abstraction). Provider configuration declares `default`/`failover`/`routing` from the first implementation, though only `default` is functional in the first release.

**E4. Sequencing note.** None of E1–E3 currently occupies a scheduled sprint — they are designed and frozen, awaiting a dedicated implementation-planning pass, the same state Maintenance/CRM were in before their own design sessions happened. Phase 2's next scheduled sprint (Sprint 2.5, Family relationships) is unaffected by and independent of this addendum.

---

## Addendum F: Administration Platform — Full Internal Architecture (2026-07-14)

The implementation-planning pass Addendum E deferred ("its final internal code organization... is deferred to Administration's own implementation-planning pass") is now complete, run as a dedicated multi-session review independent of any scheduled sprint, the same sequencing already used for Family and for Addendum E itself. Full detail lives in `docs/ADMINISTRATION_PLATFORM.md` (the companion Blueprint, parallel in weight to this document and to `docs/ADMIN_PLATFORM.md`), `docs/adr/0016` through `0022`, and `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` (execution). Summarized here since it substantially details a module named in §1's table and formalized in Addendum E1.

**F1. The word "Settings" is retired from this project's architecture vocabulary.** Administration is organized around five durable questions (who are we / who can act / what is allowed / how do we reach the world and what do we own to do it / can we prove it), realized through ten named capabilities (Organizational Identity & Structure, Access Governance, Policy & Configuration Governance, Digital Experience Delivery, Communication & Engagement, Connectivity & Interoperability, Asset & Facility Stewardship, Observability & Health Policy, Governance/Risk/Compliance, Platform Extensibility & Product Lifecycle) — never a flat feature list, never a single "Settings" table.

**F2. Administration Platform's own schema is permanently bounded to four shapes** (Configuration Registry, Provider Registry/Credential Vault, Package/Snapshot artifacts, the Experience Layer's derived compilations), enforced by an architecture test mirroring Sprint 3.1's contract-declaration scanner. It never owns Content, Reference/Master Data, or Business Rules — those stay owned by whichever Domain or Foundation module already governs them, per this Blueprint's own module-boundary rule (§2), merely administered, never re-implemented, by Administration Platform.

**F3. Business Rules (Promotion, Admission, Attendance, Grading rules; Fee/Discount Policies) generalize Addendum A5's Billing Policy pattern** into a named, reusable **Effective-Dated Business Policy pattern**, added to §6/§13's shared-pattern catalog alongside a new **Registry Pattern** and **Content Lifecycle Pattern**. None of the three are owned by Administration Platform; each is applied by the Domain module the rule actually belongs to.

**F4. Sequencing note.** As with Addendum E, none of this occupies a scheduled Phase-sequence sprint — `docs/ADMINISTRATION_PLATFORM_PLAYBOOK.md` runs as an independent Foundation Track, gated on its own internal dependencies (see that document), not on the backend Phase sequence's own ordering.
