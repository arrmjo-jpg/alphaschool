# Domain 6: HR

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** Canonical — **Reference Blueprint, certified and frozen 2026-07-27.** Foundational architecture integrated 2026-07-26 (BUS-0024–0029); all 24 canonical sections completed the same day (HR Reference Blueprint Completion Sprint); Freeze Preparation (2026-07-27) found and closed two real citation gaps; Certification found no remaining architectural or governance blocker. See Verdict. · **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) (Academic Department cross-reference), [BUS-0019](../adr/business/0019-academic-assignment-model.md) (the pattern this domain's own BUS-0025 formalizes and validates), [BUS-0024](../adr/business/0024-hr-organizational-structure.md), [BUS-0025](../adr/business/0025-hr-assignment-model.md), [BUS-0026](../adr/business/0026-employee-as-person.md), [BUS-0027](../adr/business/0027-contract-model.md), [BUS-0028](../adr/business/0028-employment-lifecycle.md), [BUS-0029](../adr/business/0029-position-lifecycle.md), [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) (BUS-0025's Employment/Position Assignment separation is a direct instance of this pattern) · **Related Domains:** [Academic](academic.md) (consumes Employee/Position for Teacher/Homeroom/Coordinator Assignment, reusing this domain's own Assignment model), [Learning](learning.md) (consumes Employee/Position for Instructor/TA staffing Participation, same mechanism), [Reception](reception.md) (consumes Employee/Department for host resolution), Payroll (future, peer domain), [Health Clinic](health-clinic.md) / [School Operations](school-operations.md) / [Smart Campus](smart-campus.md) (subscribe to Emergency Coordination alongside HR)

**Integration note.** This document previously carried explicit **Not Yet Defined** markers for Employee-to-Position assignment mechanics, Contract's relationship to Employment, and several lifecycle questions. A dedicated Discovery pass, two foundational Architecture Reviews (Organization Structure, Assignment Model), and six formal ADRs (BUS-0024–0029) have since resolved those specific gaps. This integration folds their conclusions into the document; nothing here is a new decision beyond what those ADRs already state.

## I. Foundation

### 1. Domain Purpose

Governs the employee lifecycle — hire through termination — and the organizational structure employees sit within.

### 2. Business Scope

Employee profile, Employment lifecycle (BUS-0028), Organization Structure (Department/Position/reporting line, BUS-0024), Position Assignment (BUS-0025), Contract (BUS-0027), Leave, Recruitment, Performance, Training.

### 3. Business Objects

**Submodules (internal grouping):** Employees · Organization Structure · Attendance · Leave · Contracts · Recruitment · Performance · Training

**Master Data:**
- **Employee** — a Person-specialization (BUS-0026), per `ADR-0001`. Employee = Person + an active or historical Employment relationship, the same shape already used for Student and Applicant.
- **Department** — self-referential (`parent_department_id`, BUS-0024), Branch reference nullable (populated for branch-specific Departments, null for centralized/Organization-wide ones). Non-destructive lifecycle — never hard-deleted, only marked Inactive.
- **Position** — belongs to exactly one Department (BUS-0024); carries its own self-referential "reports to" reference, defaulting to the Department hierarchy's own head, explicitly overridable per-Position for matrix/cross-functional cases. Lifecycle states (BUS-0029): Open, Frozen, Eliminated (stored); Filled is derived, never stored — true whenever an active Position Assignment references it.

**Transaction (period-scoped, effective-dated):**
- **Employment** — Person ↔ Organization/Branch, Capacity = "Employee" (BUS-0025), the continuous fact of being employed at all. Lifecycle states (BUS-0028): Probation → Active → (optionally) On Leave → Terminated / Resigned / Retired.
- **Position Assignment** — Person ↔ Position, Capacity = the specific Role held (BUS-0025). A distinct `ADR-0024` instance from Employment, not contained by it — a valid Position Assignment requires an active Employment as a validation constraint, not structural containment. Effective-dated, multi-cardinality-capable (concurrent positions, acting assignments as a distinct Capacity value on a concurrent Assignment).

**Policy** (Business-Rule-vs-Configuration classification, ADR-0018/ADR-0020):
- Leave policy (entitlement per type, accrual rules).
- Overtime rules.

**Versioned content:**
- **Contract** (BUS-0027) — a distinct, versioned document attached to Employment, not Employment itself. Represents a specific signed agreement (type — temporary/permanent/part-time —, terms, dates); renewal or type change publishes a new version, never an in-place edit, the same identity-vs-versioned-content pattern already proven for Subject/Subject Version and Curriculum Path/Curriculum Specification.

### 4. Business Processes

**Hire** — Employment opens in Probation state (BUS-0028) → Probation Confirmed (→ Active) or Failed (→ Terminated). **Internal Transfer / Promotion** — end the current Position Assignment, start a new one; Employment continues unbroken (BUS-0025) — no separate mechanism for the two, differentiated only by business meaning. **Acting Assignment** — a new, concurrent Position Assignment with an "Acting [Role]" Capacity, coexisting with the substantive holder's own Assignment (BUS-0025). **Branch Transfer** — mirrors Students'. **Leave Request → Approval** — via the existing Approval Engine; an approved request may place Employment into On Leave without closing it (BUS-0028). **Termination/Resignation/Retirement** — Employment closes into one of three distinct terminal states (BUS-0028). **Contract Renewal/Type Change** — publish a new Contract version, Employment unaffected (BUS-0027). **Recruitment pipeline** — Job Posting → Application → Interview → Offer, anchored to a Position already in the Open state (BUS-0029); structurally the same shape as Admissions' funnel.

### 5. Boundaries

Payroll consumes Employment/Position/Attendance data but owns none of HR's business rules. Academic consumes Employee/Position data for Teacher/Homeroom/Coordinator assignment, reusing this domain's own Position Assignment model (BUS-0025) rather than a parallel mechanism — a claim BUS-0019 made in advance and BUS-0025 now formally validates. Learning consumes Employee/Position data for Instructor/TA staffing Participation, the same mechanism. Academic Department is cross-referenced, never merged, with HR's own Department (BUS-0017/BUS-0024). Reception consumes Employee/Department for host resolution. HR subscribes to the cross-cutting Emergency Coordination service alongside Health Clinic, School Operations, and Smart Campus. In the other direction, HR itself consumes: a Subject Offering feed from Academic, driving this domain's own teacher-assignment workflows (already stated on Academic's side — academic.md §10/§20 — not previously cited back here); and Document Templates from Platform Services via the Document Engine, for Contract generation and versioning (BUS-0027; already stated on Platform Services' side).

## II. Configuration & Behavior

### 6. Configuration

Working-hours/shift definitions · **probation period length** (now precisely gates the Probation state in Employment's lifecycle, BUS-0028, rather than an ungrounded duration) · performance review cycle frequency.

### 7. Events

**Formalized by BUS-0028/BUS-0029, superseding the previously workflow-inferred placeholder list:** `EmploymentStarted` · `ProbationConfirmed` / `ProbationFailed` · `EmploymentSuspended` / `EmploymentResumed` · `EmploymentTerminated` / `EmploymentResigned` / `EmploymentRetired` · `PositionOpened` · `PositionFrozen` · `PositionEliminated`. **By direct analogy to Academic's own Assignment pattern (BUS-0019), not separately named in BUS-0025 itself:** `PositionAssignmentStarted` / `PositionAssignmentEnded`. **Still workflow-implied, not formalized by any ADR:** `LeaveRequested` / `LeaveApproved`, `EmployeeBranchTransferred`.

### 8. Automation Opportunities

Leave Request routing through the existing Approval Engine is already automatic by virtue of that Engine's own nature. **Beyond this: Not Yet Defined** — unaffected by BUS-0024–0029.

### 9. AI Opportunities

*(derived by direct analogy to already-accepted AI-opportunity patterns elsewhere on the platform, applied to HR's now-established data model — not a new capability class)* Predictive attrition/turnover-risk flagging from tenure, Employment-state, and Attendance trend data, surfaced to an HR Manager for review — the same shape as Academic's own "predictive at-risk-of-non-promotion flagging from grade/attendance trend." Recruitment-matching suggestions (candidate-to-Position fit) during the Application review step, advisory only. Both route through the unified `AIDecision` primitive (BUS-0003) — AI proposes, an HR Manager or Recruiter commits, the same discipline already binding everywhere else on this platform. No HR authority decision (hire, terminate, promote) is ever AI-finalized.

## III. Platform Integration

### 10. Integrations

Biometric/attendance-device Providers · background-check Providers for recruitment.

### 11. Extension Points

New Department hierarchy depths, addable as data — the self-referential structure (BUS-0024) already supports this without schema change. New Contract types beyond temporary/permanent/part-time, addable as Reference Data (BUS-0027). New Position Assignment Capacity values (additional Acting-role types, new staffing categories consumed by Academic/Learning), addable as Reference Data per `ADR-0024`'s own extensibility — the same mechanism Learning's own Extension Points already rely on. New Employment closure sub-categories for reporting purposes (e.g., distinguishing layoff from voluntary resignation within the existing Terminated state), addable without altering the state machine itself (BUS-0028).

## IV. Experience & Operations

### 12. Permissions

- **HR Manager** — full.
- **Department Head** — read own department; approve own department's leave requests.
- **Employee** — self-service: own record, own leave request.

*(HR Director, HR Officer, and Recruiter were evaluated as personas in a separate Workspace Architecture exercise but are not yet formal Permission entries here — that gap is unaffected by BUS-0024–0029 and remains open.)*

### 13. Mobile Features

**Employee App** — clock in/out, leave request, own profile, own schedule.

### 14. Dashboards

*(sourced from the dedicated HR Workspace Architecture exercise, not newly designed here)* The HR Dashboard is the default landing surface for operational users (HR Manager, Department Head — role-scoped; Employee stays on the separate self-service surface, per Mobile Features). Widget categories: **Approvals** (pending Leave Requests, Recruitment Offer approvals) · **Alerts** (upcoming Probation-end dates per BUS-0028, attendance/absenteeism anomalies) · **Pending Work** (Applications awaiting review, Interviews awaiting scheduling) · **KPIs** (see §16) · **Shortcuts** (initiate Hire, submit Leave Request, create Job Posting). Information hierarchy: Approvals/Alerts rank highest (demand action), Pending Work second, KPIs third (contextual), Shortcuts persistent but lowest visual weight. Every widget deep-links to its owning functional section — Approvals into Leave or Recruitment, Alerts into Employees, Pending Work into Recruitment, KPIs into Reports or Organization Structure. Role-scoped: Department Head sees own-department content only; HR Manager sees organization-wide.

### 15. Reports

Headcount by Department/Branch · turnover rate · leave balance report · attendance/absenteeism report · recruitment funnel conversion.

### 16. KPIs

Vacant Position count (BUS-0029, Open positions with no active Assignment) · headcount by Department/Branch · turnover rate · leave balance utilization · attendance/absenteeism rate · recruitment funnel conversion.

## V. Governance & Compliance

### 17. Security Classification

**Sensitive** — Employee, Employment, and Contract data carry real personal and legal weight (identity, compensation-adjacent terms, employment history), but HR's actions aren't physically or life-safety consequential the way Health Clinic's or Smart Campus's are, so it sits at the same tier as Academic and Learning rather than their Highly Sensitive tier.

### 18. Audit Requirements

Employment lifecycle transitions are audited without exception — already a Domain Invariant (§19, item 9), restated here as the audit obligation it implies. Position Assignment changes (transfers, promotions, acting assignments) are audited in full, matching Academic's own Assignment audit standard (BUS-0019) — these affect authority and reporting-line facts, not merely records. Contract version changes are audited, given their legal significance (BUS-0027). Department restructuring is audited (non-destructive per BUS-0024, but a real organizational change).

### 19. Domain Invariants

1. HR never owns Payroll's business rules — Payroll consumes Employment/Position/Attendance data but HR retains ownership.
2. HR's Department is never merged with Academic Department — cross-referenced only, by shared identifier (BUS-0017/BUS-0024).
3. Department is never hard-deleted — only marked Inactive (BUS-0024).
4. Employment and Position Assignment are never merged into one record — always two distinct, related `ADR-0024` instances (BUS-0025).
5. A Position Assignment never exists without an active Employment (BUS-0025).
6. Employee is never modeled as an identity separate from Person (BUS-0026).
7. A Contract is never edited in place once signed — only a new version (BUS-0027).
8. Contract and Employment are never merged into one entity (BUS-0027).
9. Employment lifecycle transitions are never silent — each is a named, auditable event (BUS-0028).
10. Position's "Filled" state is never independently stored — always derived from active Assignment data (BUS-0029).

### 20. Domain Contracts

**Owns:** Employee, Employment, Department, Position, Position Assignment, Contract — all outright.

**Consumes:** a Subject Offering feed from Academic (teacher-assignment workflows); Document Templates from Platform Services, via the Document Engine (Contract generation/versioning, BUS-0027).

**Publishes:** Employee/Position data to Academic (Teacher/Homeroom/Coordinator assignment) and Learning (Instructor/TA staffing), both via the Position Assignment model (BUS-0025); Employment/Position/Attendance data to Payroll (future); Employee/Department data to Reception.

**Guarantees:** full effective-dated history for Employment and Position Assignment (BUS-0025), never overwritten in place; non-destructive Contract versioning (BUS-0027); non-destructive Department lifecycle (BUS-0024); Position vacancy always derivable, never stale-stored (BUS-0029).

**Never owns:** Academic Department's curriculum/subject ownership; Payroll's calculation rules or financial data.

## VI. Strategic

### 21. Future Expansion

Full performance-management suite (goal-setting, 360 reviews) · succession planning · skills/competency tracking.

### 22. Commercial Differentiators

- **Full Historical Employment and Position Record** — effective-dated Position Assignments (BUS-0025) mean a transfer, promotion, or acting assignment is always reconstructible as-of any past date, a real advantage over systems that overwrite a "current position" field in place.
- **Institution-Agnostic Organization Structure** — Department's nullable Branch scope and Position's default-with-override reporting line (BUS-0024) support everything from a single-branch operation to a complex multi-campus, matrixed organization without redesign, the same commercial positioning already established for Academic's own institution-agnostic structure.
- **Contract Continuity Without Broken Tenure** — Contract's versioned-document model (BUS-0027) means a contract-type change (temporary to permanent) never fragments Employment history or corrupts tenure/benefits-eligibility calculations, a real procurement advantage over systems that force a choice between silent overwrites and broken service continuity.

## VII. Verification

### 23. Architecture Validation

- **Foundational architecture — resolved.** Organization Structure (BUS-0024), Assignment Model (BUS-0025), Employee identity (BUS-0026), Contract (BUS-0027), Employment lifecycle (BUS-0028), Position lifecycle (BUS-0029) — all Accepted, all cross-referenced above, all validated individually against `ADR-0001`, `ADR-0024`, the Academic Blueprint, the Learning Blueprint, and HR Workspace Architecture with no conflicts found.
- **Ownership boundaries** — no violation. Academic Department cross-reference intact; Payroll consumer-not-owner role intact.
- **Previously-flagged duplicated-responsibility finding — now closed.** `ADR-0024` is now cited in this document's header and Navigation footer (it was not, in fact, actually added when this line was first written — that self-contradiction was found and corrected during Freeze Preparation, see below). BUS-0025 formally establishes Employment/Position Assignment as its confirming instance.
- **Previously-flagged asymmetry with Learning — now closed.** Learning is now named in Boundaries (§5), Domain Contracts (§20), and the header's Related Domains.
- **Previously-flagged asymmetry with Platform Services — now closed (Freeze Preparation, 2026-07-27).** Platform Services' own document already named HR as a Document Template consumer (Contract templates); this document now cites it back in Boundaries (§5) and Domain Contracts (§20 Consumes).
- **HR Reference Blueprint Completion Sprint (2026-07-26) — all seven remaining canonical sections now carry content.** Extension Points, Dashboards, and KPIs derived from already-established sources (BUS-0024–0029's own consequences; the separate HR Workspace Architecture exercise for Dashboards and part of KPIs). Security Classification and Audit Requirements derived directly from this document's own Domain Invariants and the tier reasoning already used for Academic/Learning. AI Opportunities and Commercial Differentiators derived by direct analogy to already-accepted platform patterns and to this domain's own now-Accepted ADRs, respectively. No ADR was reopened; no architectural decision was changed.
- **HR Freeze Preparation (2026-07-27) — two real findings, both closed, documentation-only:**
  1. *ADR Traceability defect.* §23's own prior text claimed the technical `ADR-0024` was "now cited in this document's header," but the header/footer edit had never actually been made — a self-contradiction inside this document. Fixed by adding the citation for real, using the same annotated form Academic and Learning already use.
  2. *Undeclared upstream dependency, Academic → HR.* academic.md's own Public APIs and Domain Contracts sections state it publishes a Subject Offering feed to HR for teacher-assignment workflows; this document never cited that back, and neither did the master Dependency Map. Fixed by adding it to Boundaries (§5), Domain Contracts (§20 Consumes), and `BUSINESS_BLUEPRINT.md`'s Dependency Map (both the Academic row's Feeds column and this domain's own Depends-on column).
  
  No ADR was reopened and no architectural decision changed — both findings were citations missing from one side of an already-real, already-declared relationship, exactly the class of gap Freeze Preparation is meant to catch.

### 24. Verdict

**Foundational architecture: READY.** Employee, Employment, Position, Department, Contract, and Position Assignment now have a complete, cross-validated, non-conflicting design, matching the rigor Academic and Learning were certified against.

**Canonical-template completion: DONE.** All 24 sections now carry an explicit disposition — none remain blanket "Not Yet Defined." (§8 Automation Opportunities and §12 Permissions retain narrow, explicitly-scoped open items — a partial persona/automation gap each, not an undefined section — matching how Academic and Learning also carried small named gaps at their own certification.)

**Freeze Preparation: PERFORMED (2026-07-27).** ADR Traceability, Cross-Blueprint Consistency, Canonical Section Validation, Editorial Audit, and Final Governance Review all completed. Two real findings surfaced and closed (see Architecture Validation, §23) — an internal self-contradiction about `ADR-0024`'s citation, and an undeclared Academic→HR dependency. The Platform Services citation gap, previously left open, was closed in the same pass. No new architectural gaps were found; no ADR was reopened.

**Reference Blueprint Certification: PERFORMED (2026-07-27).** Nine-point certification review (Canonical Blueprint completeness, ADR traceability, cross-domain consistency, domain ownership, dependency consistency, Architecture Validation, governance compliance, editorial quality, Reference Blueprint standards) found no remaining architectural or governance blocker. One non-blocking observation was raised and correctly scoped out (a stale Learning row in `BUSINESS_BLUEPRINT.md`'s Dependency Map summary table — both source-of-truth documents, `learning.md` and this one, already agree; a documentation-synchronization item, not an architecture issue). Verdict: **READY FOR REFERENCE BLUEPRINT.**

**Freeze Record: ISSUED (2026-07-27).** HR is now a certified Reference Blueprint, joining Academic and Learning as a stable baseline for downstream domains. Purely administrative act — no ADR reopened, no Discovery reopened, no Blueprint content changed beyond this status declaration.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) · [BUS-0019](../adr/business/0019-academic-assignment-model.md) · [BUS-0024](../adr/business/0024-hr-organizational-structure.md) · [BUS-0025](../adr/business/0025-hr-assignment-model.md) · [BUS-0026](../adr/business/0026-employee-as-person.md) · [BUS-0027](../adr/business/0027-contract-model.md) · [BUS-0028](../adr/business/0028-employment-lifecycle.md) · [BUS-0029](../adr/business/0029-position-lifecycle.md) · [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)
- **Related Domains:** [Academic](academic.md), [Learning](learning.md), [Reception](reception.md); subscribes to Emergency Coordination alongside [Health Clinic](health-clinic.md), [School Operations](school-operations.md), [Smart Campus](smart-campus.md).
