# Domain 4: Students

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** Canonical — **Reference Blueprint, certified and frozen 2026-07-27.** Blueprint Integration performed 2026-07-27, folding in `BUS-0017`, `BUS-0030`, and `BUS-0031` — all fourteen Discovery Matrix items reflected. Reference Blueprint Completion performed the same day: six of seven remaining canonical sections completed (Automation Opportunities, AI Opportunities, Extension Points, KPIs, Security Classification, Audit Requirements); Dashboards remains explicitly Not Yet Defined, for a stated reason, not by default. Freeze Preparation and Certification found no blocker. See Verdict. · **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) (Curriculum Path/Specification bound into Enrollment's own period record, not a new Assignment type), [BUS-0030](../adr/business/0030-guardian-relationship-ownership.md) (Guardian/`guardian_student` owned by a future Parents/Family domain, not here), [BUS-0031](../adr/business/0031-learning-eligibility-boundary.md) (Learning's Course Offering Participation gated by Enrollment when its Actor is a Student), [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) (traceability only — `BUS-0031`'s Enrollment gate constrains a Learning-owned `ADR-0024` instance; Students does not itself own or implement an `ADR-0024` instance) · **Related Domains:** [Academic](academic.md) (publishes Year/Grade/Section/Stage/Subject/Curriculum Path/Curriculum Specification, consumed here read-only), [Admissions](admissions.md) (hands off into this domain on conversion), [Learning](learning.md) (consumes Enrollment/Person from here, per `BUS-0031`), [Accounting](accounting.md), [Health Clinic](health-clinic.md), [Smart Campus](smart-campus.md), [Reception](reception.md) (a Visit may reference a Student) — all consume Student/Enrollment; Parents/Family (future domain, per `BUS-0030`, references Student identity for its own `guardian_student` join, never the reverse)

**Integration note.** Students Architecture Discovery (2026-07-27) found twelve of fourteen boundary questions already resolved — either by frozen `DOMAIN_BLUEPRINT.md` technical law or by Academic's/Learning's own certified Reference Blueprints — and two genuine open questions (Guardian ownership, Learning's eligibility gate), each resolved by a dedicated Architecture Review and formalized as `BUS-0030`/`BUS-0031`. This Integration folds all fourteen conclusions into the document; nothing here is a new decision beyond what Discovery, the Reviews, and those two ADRs already state.

## I. Foundation

### 1. Domain Purpose

The permanent record of "this person is or was ever a student," and the mechanics of their enrollment period.

### 2. Business Scope

Student identity (permanent anchor), Enrollment lifecycle (period-scoped, its own state machine), Student Documents, Student-side Number Generator scheme. Explicitly excludes Guardian/`guardian_student` (`BUS-0030`) and any curriculum ownership (Academic).

### 3. Business Objects

**Submodules (internal grouping):** Student Profile · Enrollment · Student Documents

*(Guardian Links is deliberately not listed here — see `BUS-0030` and Domain Contracts, §20.)*

**Master Data:**
- **Student** — the permanent "this person is/was ever a student" anchor. Coarse, effective-dated `lifecycle_status` (active/alumni/withdrawn), distinct from Enrollment's own per-period status. Never directly branch-scoped — branch relevance always flows through Enrollment.

**Transaction (period-scoped, effective-dated):**
- **Enrollment** — independent top-level identity, not a Student child; the record every domain that needs to reference "this specific enrollment period" (Attendance, Grades, Fees, Behavior, Report Cards, Learning Participation per `BUS-0031`) keys off. Own status machine: (new) → Active → Promoted / Repeated / Transferred / Withdrawn / Graduated. **Suspension is a sub-status on the same Enrollment, never a new one.** Fields are never overwritten — a new row is appended on change (the platform's Versioning Pattern). Carries the student's bound Curriculum Path/Curriculum Specification version as part of its own period record (`BUS-0017`) — a track/major change is a real historical event, never a mutable field.

**Reference (consumed, never owned):**
- Curriculum Path/Curriculum Specification version, Academic Year/Grade/Section/Stage/Subject — all Academic's, referenced by Enrollment, never duplicated.

**Configuration:**
- Student ID/numbering format (Number Generator scheme) · required-document checklist per Grade/Program.

### 4. Business Processes

**Enrollment creation** — Student and first Enrollment are created together, on Admissions' conversion action; Applicant and Student are never conflated. **Promotion** — close Enrollment, open next year's at the next grade. **Repetition** — close Enrollment, open a new one at the same grade, new academic year. **Branch Transfer** — close Enrollment, open a new one at the destination branch. **Withdrawal** — close Enrollment, no new one opens; `lifecycle_status` = withdrawn. **Suspension** — a sub-status change on the same, still-open Enrollment. **Graduation** — terminal Enrollment status plus an event, not a new aggregate; `lifecycle_status` = alumni. **Return** — a new Enrollment appended to the same Student (found via duplicate detection) — Person and Student are never re-created for a returning individual. **Curriculum Path/Specification binding and change** (`BUS-0017`) — carried as part of Enrollment's own period record, the same mechanism already used for Section transfers.

### 5. Boundaries

Consumes Academic Year/Grade/Section/Stage/Subject/Curriculum Path/Curriculum Specification from Academic, read-only (Academic's own certified Blueprint states it publishes these, consumed by Students). Receives handoff from Admissions on Applicant-to-Student conversion. Publishes Enrollment and Person to Learning, which uses Person as its Course Offering Participation Actor and — per `BUS-0031` — requires an active Enrollment as a validity gate whenever that Actor is a Student. Publishes Student/Enrollment to Accounting, Transportation, Library, Health Clinic, Smart Campus, and Reception (a Visit may reference a Student), none of which own any part of it. Is referenced by the future Parents/Family domain for its own `guardian_student` join (`BUS-0030`) — Students supplies only the Student-side identity, never guardian data. **Explicit non-dependency:** HR. No direct relationship exists — Teacher/Homeroom/Coordinator staffing is entirely Academic's concern, itself consuming HR's Employee/Position via `BUS-0019`/`BUS-0025`; this is recorded so a future implementer isn't tempted to add a shortcut reference from Enrollment straight to Employee.

## II. Configuration & Behavior

### 6. Configuration

Student ID/numbering format · required-document checklist per Grade/Program. *(Default guardian-access permissions, previously listed here, is rescoped to the future Parents/Family domain per `BUS-0030` — not owned here.)*

### 7. Events

Derived directly from the already-frozen state machine in §3/§4, not a new design: `StudentEnrolled` · `StudentPromoted` · `StudentRepeated` · `StudentBranchTransferred` · `StudentWithdrawn` · `StudentSuspended` / `StudentReinstated` · `StudentGraduated` · `StudentReturned` (Return case, duplicate-detection path).

### 8. Automation Opportunities

The Return workflow's identity matching is already automatic — `DOMAIN_BLUEPRINT.md` names duplicate detection as the mechanism that finds a returning individual's existing Student record, rather than requiring manual lookup. Enrollment transitions driven by Academic's own Promotion/retention decision (Academic's own Architecture Validation names this decision as audited) execute automatically once that decision is finalized — Students' role is automatic execution of an upstream decision it doesn't own. **Beyond this: Not Yet Defined.**

### 9. AI Opportunities

*(derived by analogy to already-accepted AI-opportunity patterns elsewhere on the platform, applied to Students' own already-stated Reports and workflows — not a new capability class)* Predictive withdrawal/attrition-risk flagging from attendance and grade trends, surfaced to a Registrar for review — the same shape as Academic's own predictive at-risk-of-non-promotion flagging, applied to this domain's own already-named "withdrawal/attrition rate and stated reasons" report. AI-assisted duplicate-match suggestions for the Return workflow — a candidate match proposed for human confirmation before a new Enrollment is appended to an existing Student — the same shape as Reception's suggested-host lookup. Both route through the unified `AIDecision` primitive (BUS-0003) — AI proposes, a Registrar commits. No Enrollment-status transition is ever AI-finalized.

## III. Platform Integration

### 10. Integrations

Government student-ID registries, where a jurisdiction requires it — a Compliance-domain concern this domain feeds.

### 11. Extension Points

New Withdrawal-reason sub-categories for reporting purposes, addable without altering the Enrollment state machine itself — the same shape HR's own Extension Points already established for Employment closure sub-categories (`BUS-0028`). New required-document checklist items per Grade/Program, addable as Reference Data. New Student ID/numbering formats, addable via the platform's existing Number Generator configuration, no schema change. New Curriculum Path/Specification track types (owned by Academic) bind into Enrollment's existing period-record mechanism without requiring any change on Students' side (`BUS-0017`).

## IV. Experience & Operations

### 12. Permissions

- **Registrar** — full Enrollment management.
- **Teacher** — read-only on own students.
- **Parent** — read-only on own child, via the Student & Parent App. *(The underlying guardian-relationship verification and access-grant rules themselves belong to the future Parents/Family domain per `BUS-0030`; this entry covers only the read grant Students itself exposes once that relationship is established.)*

### 13. Mobile Features

**Student & Parent App** — profile, enrollment status, documents. *(Per `BUS-0030`, the Parent-facing half of this app draws guardian identity from the future Parents/Family domain, not from Students — the same cross-domain aggregation shape HR's own Dashboard already uses.)*

### 14. Dashboards

**Not Yet Defined — explicitly, not by default.** Unlike HR, Students has not been through a dedicated Workspace Architecture exercise (personas, widget categories, information hierarchy, navigation). Assembling one now, even from adjacent already-stated material (Permissions, Reports, Events), would mean designing new workspace structure rather than completing existing documentation — outside what this sprint is scoped to do ("Do NOT redesign architecture"). A dedicated Students Workspace Architecture pass, mirroring HR's own, would need to happen first; that is a documentation exercise, not a Discovery/Review/ADR, but it is not proposed or performed here.

### 15. Reports

Enrollment trend over time · withdrawal/attrition rate and stated reasons · demographic breakdown.

### 16. KPIs

Active Enrollment count · new-Enrollment volume · withdrawal/attrition rate · demographic distribution (Grade/Program/Branch).

## V. Governance & Compliance

### 17. Security Classification

**Sensitive** — Student and Enrollment data carry real personal and legal weight (identity, academic history, guardian-relationship-adjacent facts), but this domain's own actions aren't physically or life-safety consequential, so it sits at the same tier as Academic, Learning, and HR rather than Health Clinic's or Smart Campus's Highly Sensitive tier.

### 18. Audit Requirements

Enrollment transitions are audited without exception — the append-only Versioning Pattern already guarantees the underlying data-integrity mechanism; this states the audit *obligation* that mechanism exists to serve, the same restatement HR performed for its own Employment transitions. Suspension changes are audited (a sub-status change on the same Enrollment, but a real disciplinary/administrative fact). Curriculum Path/Specification binding changes are audited, matching Academic's own standard for the same fact ("Curriculum Path/Specification changes audited... they affect the transcript"). Withdrawal and Graduation are audited as terminal-state transitions.

### 19. Domain Invariants

1. Student and Enrollment are never merged — Enrollment is independent top-level identity, not a Student child.
2. Student is never directly branch-scoped — branch relevance always flows through Enrollment.
3. Enrollment fields are never overwritten — a new row is appended instead.
4. Suspension is never a new Enrollment — always a sub-status on the same Enrollment.
5. Person and Student are never re-created for a returning individual — only a new Enrollment is appended to the existing chain.
6. Applicant and Student are never conflated — conversion is a distinct, explicit action.
7. Guardian and `guardian_student` are never owned by Students — consumed only (`BUS-0030`).
8. A Course Offering Participation whose Actor is a Student is never valid without an active Enrollment gate (`BUS-0031`).
9. Curriculum Path/Curriculum Specification version is never a mutable field on Enrollment — a track/major change is a real historical event (`BUS-0017`).
10. Graduation is never a new aggregate — a terminal Enrollment status plus an event.

### 20. Domain Contracts

**Owns:** Student, Enrollment, Student Documents — all outright.

**Consumes:** Academic Year/Grade/Section/Stage/Subject/Curriculum Path/Curriculum Specification, from Academic; Applicant handoff, from Admissions.

**Publishes:** Enrollment and Person to Learning (Course Offering Participation Actor and, per `BUS-0031`, its validity gate when the Actor is a Student); Student/Enrollment to Accounting, Transportation, Library, Health Clinic, Smart Campus, Reception.

**Referenced by (not published to):** the future Parents/Family domain, for its own `guardian_student` join (`BUS-0030`) — Students supplies only the Student-side identity reference.

**Guarantees:** full append-only Enrollment history, never overwritten in place; Student identity permanence — a returning individual is never re-created, only a new Enrollment appended.

**Never owns:** Curriculum content or Academic Department (Academic); Course/Course Offering or Participation mechanics (Learning); any Employee/Position fact (HR — no relationship exists); Guardian or `guardian_student` (`BUS-0030`).

## VI. Strategic

### 21. Future Expansion

Graduation handoff into the Alumni domain (later phase) · cross-branch student mobility analytics.

### 22. Commercial Differentiators

- **Full Historical Enrollment Record** — append-only Enrollment history means a student's entire academic journey (promotions, repetitions, transfers, suspensions, a Withdrawal→Return chain) is always reconstructible as-of any past date, not just current state — a real advantage over systems that overwrite a "current grade/section" field in place.
- **Identity Permanence Across Re-Enrollment** — a returning student is never re-created as a new identity; duplicate-detection appends a new Enrollment to the same, continuous Student record, avoiding the fragmented/duplicate-student problem common in competing systems when a family re-enrolls after a gap.
- **Clean Multi-Domain Composability** — Enrollment's independent top-level identity lets Attendance, Grades, Fees, Behavior, Report Cards, and now Learning Participation (`BUS-0031`) all anchor to one unambiguous enrollment period, avoiding the "which year's grade is this?" ambiguity that plagues systems where these facts hang directly off a bare student ID.

## VII. Verification

### 23. Architecture Validation

- **Foundational architecture — resolved.** Student/Enrollment separation, the Enrollment state machine, branch scoping, and the Versioning Pattern are all settled by frozen `DOMAIN_BLUEPRINT.md` technical law; Curriculum Path/Specification binding by `BUS-0017`; Guardian/`guardian_student` ownership by `BUS-0030`; Learning's eligibility gate by `BUS-0031`. All cross-checked against `ADR-0001`, `ADR-0024`, and the Academic/Learning/HR Reference Blueprints with no conflicts found.
- **Previously-found contradiction — now closed.** v1 listed "Guardian Links" as a Students Submodule while its own Responsibilities line said guardian data is "consumed from People/Family, not owned here." Resolved in favor of the line that was already correct, per `BUS-0030` — the Submodule entry is dropped.
- **Ownership boundaries** — no violation. Curriculum/Academic Department ownership intact (Academic); Course/Participation mechanics intact (Learning); Guardian/`guardian_student` correctly deferred to the future Parents/Family domain (`BUS-0030`); HR correctly excluded as a non-dependency.
- **Bidirectional citation check** — Academic's certified Blueprint already states it publishes to Students; Learning's certified Blueprint already states Students (Enrollment, Person) as an Input — both now precisely scoped by this document and by `BUS-0031`, not contradicted.
- **Students Reference Blueprint Completion (2026-07-27)** — six of seven remaining canonical sections completed, each derived from already-accepted architecture (frozen `DOMAIN_BLUEPRINT.md`, `BUS-0017`/`0030`/`0031`, and direct analogy to already-established platform patterns — Academic's predictive-flagging shape, Reception's suggested-match shape, HR's Extension Points/Audit Requirements shape). No architecture was redesigned, no ADR reopened or created. **Dashboards was found genuinely non-completable within this sprint's scope** — unlike HR, no dedicated Workspace Architecture exercise exists for Students to draw from, and assembling one now would mean designing new workspace structure, not completing documentation. Left explicitly Not Yet Defined rather than fabricated.

### 24. Verdict

**Foundational architecture: READY.** Student, Enrollment, and their boundaries with Academic, Learning, the future Parents/Family domain, and the explicit HR non-dependency now have a complete, cross-validated, non-conflicting design, matching the rigor Academic, Learning, and HR were certified against.

**Blueprint Integration: DONE.** All fourteen items from the Students Architecture Discovery Matrix are reflected — twelve as direct documentation, two (`BUS-0030`, `BUS-0031`) as formal ADRs folded in.

**Reference Blueprint Completion: DONE, with one stated exception.** Automation Opportunities, AI Opportunities, Extension Points, KPIs, Security Classification, and Audit Requirements are complete. **Dashboards remains Not Yet Defined** — not a gap in this sprint's effort, but an honest finding that it depends on a Workspace Architecture exercise this domain hasn't had, the same prerequisite HR's own Dashboards section depended on before it could be written.

**Freeze Preparation: PERFORMED (2026-07-27).** Nine-point governance review found no blocker. Dashboards' absence assessed and classified as a documented, certifiable exception (Option B) — not a defect, not a gap in effort. One cross-blueprint observation was found (`learning.md`'s pre-existing "academically-tied vs. independent Offering" registration mechanism, uncited against `BUS-0031`) and correctly scoped as a tracked, non-blocking external maintenance item — see `BUSINESS_BLUEPRINT.md`'s Open Architecture Questions, not resolved here.

**Reference Blueprint Certification: PERFORMED (2026-07-27).** Nine-point certification review (Canonical Blueprint completeness, ADR traceability, cross-domain consistency, domain ownership, dependency consistency, Architecture Validation, governance compliance, editorial quality, Reference Blueprint standards) found no remaining blocker. Verdict: **READY FOR REFERENCE BLUEPRINT.**

**Freeze Record: ISSUED (2026-07-27).** Students is now a certified Reference Blueprint, joining Academic, Learning, and HR as a stable baseline for downstream domains. Purely administrative act — no ADR reopened, no Discovery reopened, no Blueprint content changed beyond this status declaration.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) · [BUS-0030](../adr/business/0030-guardian-relationship-ownership.md) · [BUS-0031](../adr/business/0031-learning-eligibility-boundary.md) · [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)
- **Related Domains:** [Academic](academic.md), [Admissions](admissions.md), [Learning](learning.md), [Accounting](accounting.md), [Health Clinic](health-clinic.md), [Smart Campus](smart-campus.md), [Reception](reception.md).
