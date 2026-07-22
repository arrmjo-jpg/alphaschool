# Domain 8: LMS (Distance Learning) — pending rename to Learning Intelligence Platform

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md), [BUS-0002](../adr/business/0002-continuous-mastery-advisory-only.md), [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md), [BUS-0004](../adr/business/0004-concept-graph-phased-adoption.md), [BUS-0005](../adr/business/0005-event-stream-core-platform-service.md), [BUS-0006](../adr/business/0006-ai-consent-deferred-to-privacy-domain.md), [BUS-0007](../adr/business/0007-learning-domain-renamed-learning-intelligence-platform.md), [BUS-0011](../adr/business/0011-course-offering-base-structure-accepted.md), [BUS-0012](../adr/business/0012-competency-framework-proposed.md), [BUS-0013](../adr/business/0013-rubrics-proposed.md), [BUS-0014](../adr/business/0014-content-authoring-workflow-proposed.md), [BUS-0015](../adr/business/0015-learning-object-repository-proposed.md), [BUS-0020](../adr/business/0020-academic-learning-boundary.md) (this domain's Course Template vs. Academic's Subject — the naming boundary is settled, the deeper content-vs-sequencing split remains Proposed) · **Related Domains:** [Academic](academic.md) (gradebook, Subject/Section — see BUS-0020 for the Subject/Course Template boundary), [Students](students.md) (Enrollment), [Accounting](accounting.md) (Pricing references Fee/Product, not owned here)

**Documentation status, added 2026-07-22 — read before relying on the sections below.** This domain's body text (Purpose through Future Growth) still reflects the original v1-template write-up and has *not* been rewritten to reflect the extensive design work done since. The following are decided or proposed, documented in `docs/adr/business/`, and not yet folded into the prose below: Course Template/Offering/Staff/Sessions base structure (BUS-0011, accepted, with three named open sub-issues), Course Template versioning (BUS-0001), Continuous Mastery as advisory-only (BUS-0002), the unified `AIDecision` primitive (BUS-0003), Concept Graph's phased adoption (BUS-0004), Event Stream (BUS-0005), AI Consent's deferral to a Privacy domain (BUS-0006), the Learning Intelligence Platform rename (BUS-0007, name proposed, acronym unresolved), four still-undecided proposals — Competency Framework (BUS-0012), Rubrics (BUS-0013), Content Authoring Workflow (BUS-0014), Learning Object Repository (BUS-0015) — and the Academic/Learning boundary (BUS-0020): Academic owns Subject (catalog/credit/prerequisite), this domain owns Course Template (content/pedagogy); the naming boundary is settled, but the deeper claim that Curriculum splits into Subject Sequencing (Academic) vs. Curriculum Content (this domain) remains Proposed. A full v1→v3 template retrofit of this domain, incorporating all of the above into the prose itself, remains a separate, undone task (see the Blueprint's Open Architecture Questions).

**Correction to the domain list this platform was originally scoped from**: Courses, Homework, Assignments, and Quizzes are submodules of LMS, not separate top-level domains — they have no independent identity or business rule outside the LMS delivery context. Listing them as peers of HR or Accounting would repeat the "nested tree vs. flat siblings" mistake already corrected elsewhere in this project's design work.

### Purpose
Governs digital teaching and learning delivery — course content, assignments, assessment delivery, remote/blended learning. Distinct from Academic's *structural* framework (which subjects, which sections exist) and from Examinations' *formal* exam administration (later phase) — LMS is the day-to-day digital teaching surface.

### Responsibilities
Course content management, homework/assignment distribution and submission, quiz/formative-assessment delivery, online class session scheduling, gradebook sync back to Academic.

### Submodules
Courses · Homework · Assignments · Quizzes · Online Sessions · Content Library

### Master Data
**Course** — referenced by Homework, Assignments, and Quizzes. A real entity with its own lifecycle (published, archived), not a value.

### Configuration
Late-submission policy · quiz attempt limits · auto-grading rules for objective questions · content visibility windows.

### Business Workflows
Course publish cycle · assignment distribution → submission → grading → gradebook sync into Academic · online session scheduling → attendance capture.

### Permissions
- **LMS Manager** — full.
- **Teacher** — create/grade content for own courses.
- **Student** — submit work, take quizzes.
- **Parent** — read-only visibility into child's submissions and grades.

### Reports
Assignment completion rate · quiz score distribution · course engagement (content views, time spent) · online session attendance.

### Mobile Applications
- **Student & Parent App**: assignment list, submission, grades, join-online-class.
- **Employee App**: teacher's course management and grading queue.

### Integrations
This domain has the heaviest Provider Model need on the platform — every one of these must be swappable without a redesign, per the brief's own example:

- **Meeting Provider** — Zoom, Google Meet, Microsoft Teams, future AlphaSchool Meeting.
- **LMS-Content Sync Provider** — Google Classroom, Microsoft 365 Education.
- **Storage Provider** — already generically solved by Media/Provider Registry (S3, R2, Local, Azure Blob already modeled this exact way).

### Cross-Domain Dependencies
Consumes Academic's Subject/Section structure and Students' Enrollment (who's in which class). Grades flow **into** Academic's gradebook — LMS produces grade data, but Academic owns the rule for what a grade means and where the authoritative record lives.

### Future Growth
AI-assisted grading and feedback, adaptive learning paths, plagiarism detection integration, offline-mode content sync for low-connectivity regions.

### Commercial Differentiators
*(Referenced in Commercial Differentiators discussions but not yet written as its own section here — tracked as part of the v1→v3 retrofit.)* Offline Mode, AI Grading, vendor-independent Meeting Provider, and Marketplace Extensions (the last of which depends on BUS-0015's Learning Object Repository actually being built) have all been named informally; none are yet a properly documented section.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0001](../adr/business/0001-course-template-versioning.md) · [BUS-0002](../adr/business/0002-continuous-mastery-advisory-only.md) · [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) · [BUS-0004](../adr/business/0004-concept-graph-phased-adoption.md) · [BUS-0005](../adr/business/0005-event-stream-core-platform-service.md) · [BUS-0006](../adr/business/0006-ai-consent-deferred-to-privacy-domain.md) · [BUS-0007](../adr/business/0007-learning-domain-renamed-learning-intelligence-platform.md) · [BUS-0011](../adr/business/0011-course-offering-base-structure-accepted.md) · [BUS-0012](../adr/business/0012-competency-framework-proposed.md) · [BUS-0013](../adr/business/0013-rubrics-proposed.md) · [BUS-0014](../adr/business/0014-content-authoring-workflow-proposed.md) · [BUS-0015](../adr/business/0015-learning-object-repository-proposed.md)
- **Related Domains:** [Academic](academic.md), [Students](students.md), [Accounting](accounting.md).
