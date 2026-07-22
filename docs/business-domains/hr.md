# Domain 6: HR

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v1 (retrofit-pending to v3) · **Related ADRs:** [BUS-0017](../adr/business/0017-academic-organizational-structure.md) (Academic Department is a distinct aggregate, cross-referenced to HR's Department, never merged), [BUS-0019](../adr/business/0019-academic-assignment-model.md) (Teacher/Coordinator assignment reuses HR's own Position/Assignment pattern) · **Related Domains:** [Academic](academic.md) (consumes Employee/Position data for Teacher/Homeroom/Coordinator assignments; Academic Department cross-references HR's Department by shared identifier, not merged), [Reception](reception.md) (consumes Employee/Department to resolve visit hosts and route internal correspondence), Payroll (future, peer domain), [Health Clinic](health-clinic.md) / [School Operations](school-operations.md) / [Smart Campus](smart-campus.md) (subscribe to Emergency Coordination alongside HR)

### Purpose
Governs the employee lifecycle — hire through termination — and the organizational structure employees sit within.

### Responsibilities
Employee profile, Employment periods (mirrors Enrollment's design exactly — already frozen), Position/Department structure, Leave, Recruitment, Performance, Training.

### Submodules
Employees · Attendance · Leave · Contracts · Recruitment · Performance · Training

**Correction to the example structure as given**: Payroll is not a submodule of HR ("Payroll Integration") — Payroll is a peer domain (§ below). HR *feeds* Payroll (Employment, Position, Attendance data); it doesn't contain it. The two are kept separate specifically because payroll's financial-data audit and security requirements differ from ordinary people-management concerns.

### Master Data
**Department, Position** — both referenced externally: Employees reference Department; Employment history references Position.

### Configuration
Leave policy (entitlement per type, accrual rules) · working-hours/shift definitions · overtime rules · probation period length · performance review cycle frequency.

### Business Workflows
Hire (Employment period opens) · Leave Request → Approval (via the existing Approval Engine) · Branch Transfer (mirrors Student's) · Termination/Resignation (Employment period closes) · Recruitment pipeline (Job Posting → Application → Interview → Offer — structurally the same shape as Admissions' funnel, worth reusing that pattern rather than inventing a second one).

### Permissions
- **HR Manager** — full.
- **Department Head** — read own department; approve own department's leave requests.
- **Employee** — self-service: own record, own leave request.

### Reports
Headcount by Department/Branch · turnover rate · leave balance report · attendance/absenteeism report · recruitment funnel conversion.

### Mobile Applications
The **Employee App** is primarily this domain's surface: clock in/out, leave request, own profile, own schedule.

### Integrations
Biometric/attendance-device Providers (fingerprint/face-recognition hardware varies by vendor — a genuine Provider category) · background-check Providers for recruitment.

### Cross-Domain Dependencies
Payroll consumes Employment/Position/Attendance data to run calculations but owns none of HR's business rules — a clean owner/consumer split. Academic consumes Employee/Position data for Teacher, Homeroom, and Coordinator/Department-Head assignments — all modeled as effective-dated Assignment aggregates reusing this domain's own Position/Role/Assignment pattern (BUS-0019), not a parallel mechanism. Academic Department is a separate aggregate from this domain's own Department — cross-referenced by shared identifier, never merged (BUS-0017): HR owns staffing/reporting-line/budget, Academic Department owns curriculum/subject ownership.

### Future Growth
Full performance-management suite (goal-setting, 360 reviews), succession planning, skills/competency tracking.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [Academic](academic.md); subscribes to Emergency Coordination alongside [Health Clinic](health-clinic.md), [School Operations](school-operations.md), [Smart Campus](smart-campus.md).
