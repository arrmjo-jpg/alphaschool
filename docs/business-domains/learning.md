# Domain 8: Learning

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** Canonical (migrated 2026-07-26 — reorganized into the 7-cluster canonical structure; Mobile Features and Reports restored from this domain's pre-rewrite v1 file, where they existed before being dropped during the Participation Model rewrite; Commercial Differentiators written from the same named-but-never-documented items that v1 file already flagged; Automation Opportunities, AI Opportunities, Provider Slots, Extension Points, Dashboards, KPIs, Security Classification, and Audit Requirements derived from content already stated elsewhere in this document, not newly invented) · **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md), [BUS-0002](../adr/business/0002-continuous-mastery-advisory-only.md), [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md), [BUS-0004](../adr/business/0004-concept-graph-phased-adoption.md), [BUS-0005](../adr/business/0005-event-stream-core-platform-service.md), [BUS-0006](../adr/business/0006-ai-consent-deferred-to-privacy-domain.md), [BUS-0007](../adr/business/0007-learning-domain-renamed-learning-intelligence-platform.md), [BUS-0011](../adr/business/0011-course-offering-base-structure-accepted.md) (all three sub-issues now Resolved by this Blueprint), [BUS-0012](../adr/business/0012-competency-framework-proposed.md), [BUS-0013](../adr/business/0013-rubrics-proposed.md), [BUS-0014](../adr/business/0014-content-authoring-workflow-proposed.md), [BUS-0015](../adr/business/0015-learning-object-repository-proposed.md), [BUS-0020](../adr/business/0020-academic-learning-boundary.md), [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) (🟢 Accepted — this Blueprint was its validating consumer), [ADR-0025](../adr/0025-shared-platform-pattern-lifecycle.md) · **Related Domains:** [Academic](academic.md) (Subject/Course Template boundary, BUS-0020; optional Subject Offering reference), [Students](students.md) (Enrollment, Person), [HR](hr.md) (Employee/Position for staffing Participation), [Platform Services](platform-services.md) (Document Template, Number Generator, Notification)

**Design history.** This domain was documented only after a dedicated Bounded Context Review, a two-phase Architecture Discovery, and — most consequentially — a Participation Model investigation that surfaced a platform-wide gap: "Person + Target + Capacity + effective period" had already been independently implemented three times (Employment, Enrollment, Teacher/Homeroom/Coordinator Assignment) without ever being named. That investigation was elevated out of this domain entirely and resolved as [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md), a pattern this Blueprint validated as its first real consumer — since Accepted.

**Correction to the domain list this platform was originally scoped from**: Courses, Homework, Assignments, and Quizzes are submodules of Learning, not separate top-level domains — they have no independent identity or business rule outside the delivery context.

## I. Foundation

### 1. Domain Purpose

Governs digital teaching and learning delivery: content authored once (Course Template, versioned), delivered many times (Course Offering), consumed by Actors in various Capacities via the platform's Role Assignment pattern ([ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)), producing Progress, Grades, and Certificate-eligibility facts that feed the institution's formal record without owning that record. Distinct from Academic's *structural*/registrar function (BUS-0020) and from Examinations' *formal* exam administration (unbuilt, later phase).

**Owns:** Course Template (versioned content), Course Offering, Session, Learning Progress, Grade production (not grade meaning), Certificate-eligibility facts (not certificate generation).

**Does not own:** Subject/Subject Offering catalog identity (Academic), Person/Student/Employee identity (Core/Students/HR), Participation relationship metadata ([ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)), Approval mechanics, document generation, numbering, or notification delivery (all Platform Services/Foundation).

### 2. Business Scope

Content Authoring & Versioning · Course Delivery & Scheduling · Participation & Staffing (consumed, never owned) · Assessment & Progress · Certification (eligibility only, not generation).

**Explicitly still Proposed, not upgraded by this migration:** Competency Framework (BUS-0012), Rubrics (BUS-0013), Content Authoring Workflow (BUS-0014), Learning Object Repository (BUS-0015).

### 3. Business Objects

| Object | Classification | Note |
|---|---|---|
| Course Template | Master Data, versioned (BUS-0001) | Owner: Learning |
| Course Offering | Master Data, period-scoped | Owner: Learning. **Optionally references Academic's Subject Offering** — nullable, populated only when academically-tied; null for Public/Paid/Free/Corporate Training. Independent of Academic's lifecycle in every case. |
| Session | Sub-object of Course Offering, each carrying its own Meeting Provider reference | Resolved BUS-0011's third open sub-issue (Meeting Provider was Offering-level, not session-level) within Learning's own modeling — no new Foundation mechanism required |
| Participation (staffing and learning) | **Not owned by Learning** — an instance of [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)'s Actor↔Governed-Resource pattern, Target = Course Offering or Course Template, Capacity = Learner / Instructor / Lead Instructor / TA / Guest Lecturer / Observer / Content Maintainer | Resolved BUS-0011's first two open sub-issues (single-valued Enrollment per Offering; single-cardinality Teacher) as a direct consequence, not a separate patch |
| Content Authorship (point-in-time) | An attribution fact carried by Course Template's own Versioning metadata | Consumes the existing Versioning pattern's "created by" convention — not a new mechanism, and deliberately not a standing Capacity |
| Content Review | Consumes the Approval Engine directly: Draft → Review → Approved | No standing "Reviewer" relationship needed for the ordinary case |
| Learning Progress | Learning-specific business data, referencing a Participation instance | Never an attribute of the Participation record itself |
| Grade | Same as Progress; meaning owned by Academic | BUS-0020 boundary |
| Certificate-eligibility fact | Learning-specific; triggers Platform Services' Document Template once Academic's Certificate Eligibility Rule is satisfied | — |
| External/non-institutional participant | **A Person record** — never a bespoke "Learner" entity | Forced consequence of [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md)'s Terminology gate |

### 4. Business Processes

**Course Template Authoring & Publication** — Draft authored (attribution via Versioning metadata) → submitted for Review (Approval Engine, consumed) → Approved → published as a new version. Prior versions stay queryable, never edited in place (Invariant 1).

**Course Offering Scheduling** — Template version selected → Offering created, optionally referencing a Subject Offering → Sessions defined, each with its own Meeting Provider → staffing Participation records created (Instructor/TA/Lead Instructor Capacities against the Offering).

**Registration into a Course Offering** — for academically-tied Offerings, resolves from Students' Enrollment (Grade-based auto-participation); for independent Offerings, an existing or newly-created Person is directly assigned a Learner-Capacity Participation record.

**Progress & Assessment** — each Learner-Capacity Participation accumulates Progress → optional Continuous Mastery signal (BUS-0002, advisory only) → Instructor-submitted Grade feeds Academic's gradebook rule.

**Certificate Issuance** — completion evaluated against Academic's Certificate Eligibility Rule → Platform Services generates the document → Learning records the eligibility fact only.

### 5. Boundaries

- **Academic** — Learning consumes Subject and, optionally, Subject Offering from Academic, strictly read-only. Academic never depends on Learning.
- **Students** — consumes Enrollment and Person identity via ADR-0024, never owns either.
- **HR** — consumes Employee/Position for Instructor/TA staffing, riding the same ADR-0024 mechanism Academic's own Teacher Assignment already uses.
- **Platform Services** — consumes Document Template, Number Generator (once centralized), Notification — none reimplemented.
- **Foundation/Core** — consumes ADR-0024 for all Participation, and the Approval Engine for Template review.

## II. Configuration & Behavior

### 6. Configuration

Genuine Configuration only — Business Rules are excluded per the ADR-0018/ADR-0020 classification.

| Field | Scope |
|---|---|
| Late Submission Grace Period | Organization default, Course-Offering-override |
| Quiz Maximum Attempts | Organization default |
| Auto-Grading Enabled (objective questions) | Organization |
| Content Visibility Window | Organization |

Any compound auto-grading *logic*, if it exists beyond a simple toggle, is a Business Rule under ADR-0020, not Configuration.

### 7. Events

`CourseTemplatePublished` · `CourseOfferingScheduled` (consumers: HR, Academic when tied) · `ProgressRecorded` · `GradeSubmitted` (consumer: Academic's gradebook rule) · `CertificateEligibilityMet` (consumers: Platform Services, Academic). Participation creation/end events belong to ADR-0024's pattern itself, not to Learning.

### 8. Automation Opportunities

*(derived from Business Processes §4, not new capability)* Grade submission auto-syncs into Academic's gradebook rule with zero manual step once an Instructor submits it · Certificate-eligibility evaluation auto-triggers on course completion rather than requiring a manual check · Continuous Mastery signal (BUS-0002) auto-computes from accumulated Progress data, always advisory, never auto-finalizing a Grade.

### 9. AI Opportunities

*(the same three capabilities already named in this domain's original scope, restored, not invented)* AI-assisted grading and feedback on open-ended submissions, always instructor-confirmed before affecting an official Grade · adaptive learning paths surfaced as a suggestion, never an auto-applied change to a student's Course Offering assignment · plagiarism detection integration as a flag for human review, never an automatic rejection. All AI output routes through the unified `AIDecision` primitive (BUS-0003) — Learning is where that primitive originated (BUS-0002's Continuous Mastery), so it carries no retroactive-correction debt.

## III. Platform Integration

### 10. Integrations

**Provider Slots** *(restored from this domain's pre-rewrite Integrations section)*: **Meeting Provider** — Zoom, Google Meet, Microsoft Teams, future AlphaSchool Meeting, now scoped per-Session (§3) rather than per-Offering. **LMS-Content Sync Provider** — Google Classroom, Microsoft 365 Education. **Storage Provider** — already generically solved by Media/Provider Registry.

**Inputs:** Academic (Subject, optional Subject Offering), Students (Enrollment, Person), HR (Employee/Position). **Outputs:** Academic (Grades). **Shared Services consumed:** ADR-0024 Role Assignment pattern, Approval Engine, Document Template, Number Generator, Notification.

### 11. Extension Points

New Participation Capacity values, addable as Reference Data without schema change (per ADR-0024's own extensibility) · new Course Offering audience types beyond School/Public/Paid/Free/Corporate Training · new Meeting Provider categories · new Certificate template types, consuming Platform Services' existing Document Template mechanism.

## IV. Experience & Operations

### 12. Permissions

- **Learning Manager** — full domain administration.
- **Content Author/Maintainer** — authors and maintains Course Template content, submits for Review.
- **Instructor/Lead Instructor** — delivers a Course Offering, submits Grades.
- **Teaching Assistant** — assists within a specific Offering, limited grading delegation.
- **Learner** — consumes content, submits work, views own Progress/Grades.

### 13. Mobile Features

*(restored from this domain's pre-rewrite v1 file, adjusted to current object names)*
- **Student & Parent App**: Course Offering/assignment list, submission, Progress and Grades, join-online-Session.
- **Employee App**: Instructor's Course Offering management and grading queue.

### 14. Dashboards

*(derived from Business Processes' Review and Scheduling workflows, §4)* Active Course Offerings by term · pending Content Review queue · Progress completion overview across active Offerings.

### 15. Reports

*(restored from this domain's pre-rewrite v1 file)* Assignment/Course Offering completion rate · quiz score distribution · course engagement (content views, time spent) · online Session attendance.

### 16. KPIs

*(derived from Reports §15)* Course completion rate · average time-to-completion · Content Review turnaround time · Certificate issuance rate.

## V. Governance & Compliance

### 17. Security Classification

**Sensitive** — Progress and Grade data carry real academic and privacy weight, feeding Academic's own Sensitive-tier records (BUS-0020), but this domain's actions are not physically or life-safety consequential, so it sits at the same tier as Academic rather than the Highly Sensitive tier reserved for Health Clinic/Smart Campus.

### 18. Audit Requirements

Grade submission audited, matching Academic's own audit standard for the gradebook rule it feeds · Certificate-eligibility determinations audited · Content Review/Approval decisions audited, per the Approval Engine's own unconditional audit guarantee (ADR-0018) — no separate mechanism needed.

### 19. Domain Invariants

1. Published Course Template content is never edited in place — only a new version (BUS-0001).
2. Learning never creates or modifies Academic's Master Data — Subject/Subject Offering are read-only references.
3. Learning never owns Person, Student, or Employee identity.
4. Learning produces Grades; Academic alone owns what a Grade means (BUS-0020).
5. No new relationship metadata is ever added to a Participation record — new attributes belong to Learning's own referencing objects (Progress, Grade, Certificate-eligibility), never to the relationship itself.
6. No new Actor type is introduced without its own ADR — external participants are Person records, never a bespoke Learning-specific identity.
7. Academic never depends on Learning.

### 20. Domain Contracts

Consumes ADR-0024 for all Participation/staffing · consumes the Approval Engine for Template review · consumes Document Template for Certificates · consumes Number Generator (once centralized) and Notification · consumes Academic's Subject/Subject Offering, read-only · never modifies Academic's or Students' Master Data · produces Course Content, Progress, Grades, Certificate-eligibility facts · never owns Person identity · never introduces a new Actor type unilaterally.

## VI. Strategic

### 21. Future Expansion

BUS-0012–0015 remain Proposed, not upgraded by this migration. Corporate Training's deployment-model question (the still-open Architecture Assumption in `BUSINESS_BLUEPRINT.md`) is deferred. BUS-0006's AI Consent dependency on the unbuilt Privacy domain still blocks any AI-consent-specific Configuration.

### 22. Commercial Differentiators

*(the same items this domain's pre-rewrite file named informally but never documented as a proper section — written here for the first time, not invented fresh)*
- **Offline Mode** — content sync for low-connectivity regions, a real differentiator against LMS competitors that assume constant connectivity.
- **AI-Assisted Grading** — instructor-confirmed, never autonomous, consistent with this platform's AI-supervision discipline — a trust differentiator, not just a feature.
- **Vendor-Independent Meeting Provider** — Zoom/Meet/Teams/future-AlphaSchool-Meeting behind one Provider Slot, avoiding the single-vendor lock-in common among competing LMS platforms.
- **Marketplace Extensions** — depends on BUS-0015's Learning Object Repository actually being built; named here as the differentiator it would unlock, not claimed as already available.

## VII. Verification

### 23. Architecture Validation

- **Was any new shared mechanism invented?** Checked against every Foundation touchpoint — Participation, Approval, Document Template, Number Generator, Notification. None reinvented.
- **Was any new relationship metadata introduced outside ADR-0024's shape?** Checked — Progress/Grade/Certificate-eligibility all reference a Participation record, never extend it.
- **Did Learning-specific business data stay owned by Learning?** Checked — Progress, Grade production, Certificate-eligibility stay Learning's.
- **Course Offering Independence, verified in a dedicated pass:** Course Offering is always a Learning-owned aggregate; Subject Offering never owns or creates it; Academic may optionally be referenced but never controls its lifecycle.
- **Genuine remaining gap, not smoothed over:** Session is currently Learning-owned, not a Foundation pattern; a future domain needing the identical shape requires its own ADR-0025-lifecycle treatment.
- **Genuine remaining gap, not smoothed over:** the Person-based model for a *recurring* corporate trainee is structurally sound but thin, pending Corporate Training's still-open deployment-model question.

### 24. Verdict

**READY FOR IMPLEMENTATION.** ADR-0024's Validation Plan was satisfied on direct inspection and the pattern has since been promoted to Accepted. The two flagged gaps (Session's reusability, Corporate Training's deployment model) remain Open Questions, not blockers.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md) · [BUS-0002](../adr/business/0002-continuous-mastery-advisory-only.md) · [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) · [BUS-0004](../adr/business/0004-concept-graph-phased-adoption.md) · [BUS-0005](../adr/business/0005-event-stream-core-platform-service.md) · [BUS-0006](../adr/business/0006-ai-consent-deferred-to-privacy-domain.md) · [BUS-0007](../adr/business/0007-learning-domain-renamed-learning-intelligence-platform.md) · [BUS-0011](../adr/business/0011-course-offering-base-structure-accepted.md) · [BUS-0012](../adr/business/0012-competency-framework-proposed.md) · [BUS-0013](../adr/business/0013-rubrics-proposed.md) · [BUS-0014](../adr/business/0014-content-authoring-workflow-proposed.md) · [BUS-0015](../adr/business/0015-learning-object-repository-proposed.md) · [BUS-0020](../adr/business/0020-academic-learning-boundary.md) · [ADR-0024](../adr/0024-shared-role-pattern-actor-governed-resource.md) · [ADR-0025](../adr/0025-shared-platform-pattern-lifecycle.md)
- **Related Domains:** [Academic](academic.md), [Students](students.md), [HR](hr.md), [Platform Services](platform-services.md).
