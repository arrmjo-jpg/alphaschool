# Domain 3: Academic

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** Canonical (migrated 2026-07-26 — reorganized into the 7-cluster canonical structure; Verdict added; Policy items separated from Configuration per ADR-0018/ADR-0020; Submodules and Data Ownership absorbed into Business Objects and Domain Contracts respectively, per the canonical template's own mapping — no content changed, no decision reopened) · **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md) (reused for Subject Version), [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md), [BUS-0009](../adr/business/0009-tracking-strategy-setting-not-classification.md) (reused for catalog-year lock/float), [BUS-0017](../adr/business/0017-academic-organizational-structure.md), [BUS-0018](../adr/business/0018-academic-subject-model.md), [BUS-0019](../adr/business/0019-academic-assignment-model.md), [BUS-0020](../adr/business/0020-academic-learning-boundary.md), [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) (traceability only — BUS-0019's Assignment Engine pattern is retroactively recognized as one of ADR-0024's specializations per that ADR's own Consequences section; Academic's own mechanism is unchanged and does not depend on it) · **Related Domains:** [Students](students.md) (Enrollment references Year/Grade/Section/Curriculum Path/Curriculum Specification), [HR](hr.md) (Academic Department cross-references HR's Department; Teacher/Coordinator assignment consumes Employee/Position), [Accounting](accounting.md) (fee terms reference Academic Year), [Learning](learning.md) (Subject/Course Template boundary, BUS-0020), [School Operations](school-operations.md) (Timetable Synchronization consumes the calendar), [Inventory](inventory.md) (Annual Reusable Issue syncs to Academic Year rollover)

**Design history.** This domain was documented only after four dedicated architectural reviews, in order: (1) a general review naming Assignment effective-dating as the strongest finding; (2) a review of Academic Organizational Structure (Faculty, Academic Department, Stage, Curriculum Path) that surfaced and resolved a naming collision with the platform's existing frozen Program concept; (3) a review of the Subject model that surfaced and resolved a second naming collision, with Learning's Course Template; (4) a review of whether curriculum requirements deserve their own aggregate, resolved as Curriculum Specification. All four are captured in BUS-0017 through BUS-0020.

## I. Foundation

### 1. Domain Purpose

Governs the structural, organizational, and pedagogical framework an educational institution operates within — what a "year" and a "path" mean, how students are grouped and sequenced through subjects, who is academically accountable for what, and when. Deliberately generalized (not K-12-only) to also support Universities, Colleges, Institutes, multi-program schools, international curricula, and vocational education, without requiring architectural change per deployment type.

### 2. Business Scope

Academic Year & Terms, Grade Levels & Stage, Sections, Faculty & Academic Department, Curriculum Path & Curriculum Specification, Subject Catalog & Offerings, Timetabling, Teacher/Homeroom/Coordinator Assignment, Grading Scale.

Define the academic calendar (years, terms, exceptions) and roll it over automatically at year-end · group Grade Levels under a Stage, generalizing to University-equivalent tiers (Undergraduate/Graduate/Doctoral) via a localizable label, never a separate entity per institution type · model Faculty and Academic Department as an optional, nullable organizational tier for institutions that need it, cross-referenced to HR's own Department rather than duplicating it · define a named Curriculum Path (Track, Stream, Major, Specialization — one entity, localizable label) with an official, versioned Curriculum Specification stating its Requirements and Completion Rules · define Subjects with prerequisites, credit hours, and equivalence relationships, and schedule them into term-bound Subject Offerings · assign Teachers, Homeroom ownership, and Coordinator/Department-Head academic scope as full effective-dated history, not mutable current-value fields · generate and publish timetables and report cards.

### 3. Business Objects

**Submodules (internal grouping):** Academic Year & Terms · Grade Levels & Stage · Sections · Academic Organizational Hierarchy (Faculty · Academic Department) · Curriculum Path & Curriculum Specification · Subject Catalog, Offerings & Equivalency · Timetabling · Teacher/Homeroom/Coordinator Assignments · Grading Scale

**Master Data:**

**Academic Year, Grade Level, Section, Grading Scale** — the earliest-identified Master Data set for this domain, all genuinely referenced externally (Enrollment references Academic Year and Grade; Timetable references Section; Report Cards reference Grading Scale).

**Stage** (BUS-0017) — groups Grade Levels (Foundation/Primary/Secondary, or the University-equivalent Undergraduate/Graduate/Doctoral tier); one concept, localizable label per institution type.

**Faculty** (BUS-0017) — optional, nullable; University-only tier. Never a synonym for teaching staff anywhere in this platform's vocabulary — that word is reserved exclusively for this administrative-division sense.

**Academic Department** (BUS-0017) — a distinct aggregate from HR's Department, cross-referenced by a shared identifier, never merged. HR owns staffing/reporting-line; Academic Department owns curriculum/subject ownership and, where applicable, sits under a Faculty.

**Curriculum Path** (BUS-0017) — self-referential (`parent_curriculum_path_id`), unifying Track/Stream/Major/Specialization/"Program of Study" (degree sense) under one canonical entity. Never named "Program" or "Academic Program" — that word is permanently reserved by the platform's existing School/Kindergarten concept.

**Curriculum Specification** (BUS-0017) — versioned, official requirement and completion-policy definition for one Curriculum Path (the "catalog year" concept). Owns two sibling structures: **Requirements** (categories with a satisfaction rule each — a specific Subject, pick-N-of-M from a pool, or a minimum credit-hour threshold) and **Completion Rules** (minimum GPA, minimum credits, mandatory internship/thesis/capstone, language requirement, community-service hours — genuine policy gates, not expressible as Subject requirements).

**Subject** (BUS-0018) — the catalog-level teachable unit (discipline reference, nullable Credit Hours, prerequisites, active/retired status). Versioned via the same discipline as BUS-0001's Course Template Version. "Course" is permitted only as a display label in higher-ed-flavored deployments, never the schema/API name — that word is reserved by Learning's Course Template.

**Subject Offering** (BUS-0018) — Subject × Term × Section × Teacher × Room × Schedule; the period-scoped record Grade/Enrollment actually reference, not the abstract catalog Subject.

**Policy** (extracted from the pre-migration "Settings" list, per the canonical template's Business-Rule-vs-Configuration classification, ADR-0018/ADR-0020 — ownership unchanged, still Academic's; only the categorization corrected):

Promotion rules (minimum grade to advance) · remedial-program eligibility thresholds · certificate eligibility rules · attendance-to-academic-consequence thresholds (the threshold is Academic's; the resulting warning/suspension action belongs to Discipline, a later-phase domain).

### 4. Business Processes

**Academic Year rollover** — close year → promote/repeat/withdraw per Enrollment's already-frozen state machine → open next year. **Timetable generation and publishing.** **Report card generation cycle.** **Curriculum Path assignment/change** — effective-dated as part of Enrollment's own period record (BUS-0017), the same mechanism Enrollment already uses for Section transfers; switching Track or Major mid-year is a real historical event, never a mutable field. **Subject Offering scheduling** — the entity the deferred Timetabling engine schedules against. **Teacher/Homeroom/Coordinator Assignment** — full effective-dated Assignment aggregates (BUS-0019), reusing the existing Assignment Engine pattern directly; a mid-year Homeroom change is a normal auditable event, not a silent overwrite.

### 5. Boundaries

Feeds Students, Accounting, Learning, School Operations, and Inventory; none of them own any part of Academic's master data. **Cross-references, never merges,** HR's Department (BUS-0017). **Consumes** HR's Employee/Position data for Teacher/Coordinator assignment. See Domain Contracts (§20) for the full owns/consumes/publishes statement.

## II. Configuration & Behavior

### 6. Configuration

Catalog-year lock-vs-float policy for Curriculum Specification — deployment-wide default with a per-Program override, reusing BUS-0009's Setting-with-override mechanism rather than a new one · localizable display label for Curriculum Path (Track/Stream/Major/Specialization/Program of Study) and for Stage, per institution or region.

*(Promotion rules, eligibility thresholds, and consequence thresholds — previously listed here as "Settings" — moved to Business Objects §3 as Policy, per the canonical template's rule that Business Rules are never documented as Configuration. No change to what these are or who owns them, only where they're recorded.)*

### 7. Events

`AcademicYearRolledOver` · `SubjectRetired` · `SubjectOfferingScheduled` · `CurriculumPathChanged` · `CurriculumSpecificationVersionPublished` · `TeacherAssignmentChanged` · `HomeroomAssignmentChanged` · `CoordinatorAssignmentChanged`

### 8. Automation Opportunities

Auto-generate a draft timetable from constraints (rooms, teacher availability, Subject Offering counts) for human review, never auto-published · auto-flag scheduling conflicts before publishing · auto-apply promotion-rule outcomes pending human confirmation, never silently finalized · auto-resolve which Curriculum Specification version applies to a student based on the catalog-year lock/float Setting.

### 9. AI Opportunities

Predictive at-risk-of-non-promotion flagging from grade/attendance trend, surfaced to a Registrar for review · timetable-conflict resolution suggestions · natural-language drafting assistance for Curriculum Specification requirement text. All of the above route through the unified `AIDecision` primitive (BUS-0003) from the start — Academic is a new domain being documented after BUS-0003 was accepted, so unlike Health Clinic/School Operations/Smart Campus it carries no retroactive-correction debt here. Academic authority decisions (promotion, requirement satisfaction, graduation eligibility) are never AI-finalized — AI proposes, a Registrar or Academic Manager commits, the same discipline established for Learning's Continuous Mastery (BUS-0002).

## III. Platform Integration

### 10. Integrations

**Provider Slots: LMS-sync Provider** (Google Classroom, Microsoft 365 Education) for roster synchronization — a genuine Provider category, not hardcoded to one vendor.

**Public APIs:** Read APIs for Academic Year/Grade/Section/Stage/Subject/Curriculum Path/Curriculum Specification, consumed by Students, Accounting, Learning, and School Operations · a timetable/calendar feed consumed by School Operations' Timetable Synchronization (read-only; School Operations never owns or overrides this data) · a Subject Offering feed for HR's teacher-assignment workflows.

### 11. Extension Points

New Stage/Grade-Level labels, new Curriculum Path types and display labels, new Subject equivalence types, new Grading Scale variants for international curricula (IB, American, British, French systems) — all configuration or new rows, no redesign.

## IV. Experience & Operations

### 12. Permissions

- **Academic Manager** — full.
- **Registrar** — structural read/write, Enrollment-adjacent; owns Curriculum Path/Specification assignment.
- **Department Head / Coordinator** — read/write within own Academic Department's academic scope (BUS-0017/BUS-0019); does not grant HR Position authority.
- **Teacher** — read-only on own timetable/Subject Offerings; write on own gradebook.

### 13. Mobile Features

- **Student & Parent App**: timetable view, report card view, academic calendar, own Curriculum Path and Curriculum Specification completion progress.
- **Employee App**: teacher's own timetable and assigned Subject Offerings; own Coordinator/Homeroom assignment history.

### 14. Dashboards

Enrollment by Grade/Section · promotion/retention rate · timetable conflict report · class size distribution · curriculum-requirement completion progress board (enabled by Curriculum Specification).

### 15. Reports

Enrollment by Grade/Section · promotion/retention rate · timetable conflict report · class size distribution · curriculum requirement completion report · cross-listed/equivalency usage report.

### 16. KPIs

Promotion rate · class size distribution · timetable conflict rate · average time-to-graduation-requirement-completion (meaningful now that Curriculum Specification exists as a real entity, not just an implied concept).

## V. Governance & Compliance

### 17. Security Classification

**Sensitive** — academic records (grades, promotion decisions, curriculum standing) carry real privacy and legal weight (transcripts, accreditation), but this domain's actions aren't physically or life-safety consequential the way School Operations' or Smart Campus's are, so it sits one tier below those domains' Highly Sensitive classification.

### 18. Audit Requirements

Promotion/retention decisions audited · Curriculum Path/Specification changes audited (they affect the transcript) · Teacher/Homeroom/Coordinator assignment changes audited in full, matching Employment/Enrollment's own audit standard (BUS-0019) · Subject retirement and equivalency-mapping changes audited.

### 19. Domain Invariants

1. A published Curriculum Specification is never edited in place — only a new version, per its own versioned classification (BUS-0017).
2. Academic Department is never merged with HR's Department — cross-referenced only, by a shared identifier.
3. Academic's own Master Data — Academic Year, Grade Level, Stage, Section, Grading Scale, Faculty, Academic Department, Curriculum Path, Curriculum Specification, Subject — is never owned or modified by another domain, only consumed.
4. A student's Curriculum Path/Curriculum Specification binding is never a mutable field — always an effective-dated historical event.
5. Teacher/Homeroom/Coordinator Assignment changes are never silent overwrites — always a new effective-dated record, the prior one closed automatically.
6. "Faculty" is never used as a synonym for teaching staff.
7. Curriculum Path is never named "Program" or "Academic Program."
8. "Course" is never the schema/API name for Subject — only a permitted display label; the schema name is reserved by Learning's Course Template.
9. Academic never creates or owns an Enrollment record.
10. Academic never depends on Learning.

### 20. Domain Contracts

**Owns:** Academic Year, Grade Level, Stage, Section, Grading Scale, Faculty, Academic Department, Curriculum Path, Curriculum Specification, Subject (catalog + Offering) — outright.

**Consumes:** HR's Employee/Position data for Teacher/Coordinator assignment; cross-references (never consumes as owned data) HR's Department.

**Publishes:** Read APIs for Academic Year/Grade/Section/Stage/Subject/Curriculum Path/Curriculum Specification to Students, Accounting, Learning, and School Operations; a read-only timetable/calendar feed to School Operations; a Subject Offering feed to HR.

**Guarantees:** full effective-dated history for staffing and curriculum-path changes; non-destructive Subject retirement; localizable Curriculum Path/Stage vocabulary without schema change; institution-type-agnostic structure (Faculty/Stage nullable) without redesign per deployment.

**Never owns:** Student or Enrollment identity (Students); Employee or Position identity (HR, cross-referenced only); Course Template or pedagogical content (Learning, BUS-0020); Fee or billing data (Accounting); the document-generation engine (Platform Services).

## VI. Strategic

### 21. Future Expansion

Timetabling's scheduling engine as a deferred future Core-extraction candidate (🔵, general Academic review — Subject Offering is already the entity it would schedule against, so no redesign is needed when that work starts) · Academic Calendar separating from Academic Year as its own Master Data entity (🟡 Proposed, general Academic review — not yet decided) · the deeper Subject Sequencing (Academic) vs. Curriculum Content (Learning) split (🟡 Proposed, BUS-0020 — only the Subject/Course Template naming boundary itself is settled so far) · lesson planning tools and competency-based progression as an alternative to strict grade-level advancement (named in this domain's earliest discovery pass, still open).

### 22. Commercial Differentiators

- **Multi-Institution-Type Support Without Redesign** — the same schema supports K-12, University, College, and vocational deployments because Faculty and Stage are nullable and Curriculum Path/Curriculum Specification are institution-agnostic by construction, not retrofitted per customer — a genuine advantage over competitors architecturally locked to one institution type.
- **Localizable Academic Vocabulary** — Track/Stream/Major/Curriculum Path labeling adapts per region or institution without any schema change, valuable for international curricula and multi-country deployments where the same underlying concept goes by different names.
- **Full Historical Academic Record** — effective-dated Teacher/Homeroom/Coordinator assignments and versioned Curriculum Specifications mean a transcript, accreditation audit, or legal dispute can reconstruct exactly what applied when, a real advantage over systems that silently overwrite current-value fields.

## VII. Verification

### 23. Architecture Validation

- **No new shared mechanisms introduced.** This domain relies on, and does not reimplement: BUS-0009's Setting-with-override pattern (catalog-year lock), the Assignment Engine pattern (BUS-0019), the unified `AIDecision` primitive (BUS-0003), Platform Services' Document Template engine, and the LMS-sync Provider category.
- **No ownership violations found.** Cross-checked against Domain Contracts and Business Objects — Academic Department remains cross-referenced, never merged, with HR's Department; the Subject/Course Template boundary (BUS-0020) is intact; no section claims ownership over anything Learning, Students, HR, or Accounting already own.
- **Asymmetry is intentional, not an oversight.** Academic feeds Students, Accounting, Learning, School Operations, and Inventory in one direction by design — Academic is the structural/registrar authority these domains consume from, not a peer exchanging data bidirectionally with any of them.
- **Academic still does not depend on Learning.** No sentence in this document references consuming anything from Learning. The only Learning-related content is Academic feeding Learning (§10, §20) and the BUS-0020 naming-boundary citation — both one-directional.

### 24. Verdict

**READY FOR IMPLEMENTATION.** Three items remain open and non-blocking, none newly discovered here: Academic Calendar's separation from Academic Year (🟡 Proposed), the deeper Subject Sequencing vs. Curriculum Content split with Learning (🟡 Proposed, BUS-0020), and Grading Scale's lack of explicit versioning despite carrying the same mid-year-change risk as Curriculum Specification (flagged in an earlier Enterprise Configuration Architecture review, not yet a formal Blueprint entry — tracked here rather than silently dropped). None of the three block implementation; all three are traceable, named gaps rather than unknowns.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md) · [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) · [BUS-0009](../adr/business/0009-tracking-strategy-setting-not-classification.md) · [BUS-0017](../adr/business/0017-academic-organizational-structure.md) · [BUS-0018](../adr/business/0018-academic-subject-model.md) · [BUS-0019](../adr/business/0019-academic-assignment-model.md) · [BUS-0020](../adr/business/0020-academic-learning-boundary.md) · [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) (traceability only)
- **Related Domains:** [Students](students.md), [HR](hr.md), [Accounting](accounting.md), [Learning](learning.md), [School Operations](school-operations.md), [Smart Campus](smart-campus.md), [Inventory](inventory.md) all consume Academic's master data.
