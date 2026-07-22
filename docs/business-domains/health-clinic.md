# Domain 9: School Health Clinic

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v3 · **Related ADRs:** [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) (this domain's AI Opportunities section below still uses its own pre-BUS-0003 wording; a retroactive correction pass to reference the unified `AIDecision` primitive is tracked as an open item, not yet done) · **Related Domains:** [Students](students.md) (Parent Notifications, medical-excuse Attendance flag), [School Operations](school-operations.md) / [Smart Campus](smart-campus.md) / [HR](hr.md) (Emergency Coordination)

**A domain, not a Module and not a submodule of anything.** Worth reconciling this explicitly against vocabulary already established elsewhere rather than just asserting it: the Program-vs-Module distinction was about whether something needs its *own enrollment/identity surface* — Health Clinic doesn't enroll anyone, it operates on students already enrolled via School/Kindergarten, so on that specific axis it looks Module-shaped, the same as Summer Camp. But "Domain vs. Module" is a *different* axis — bounded-context weight: does this deserve its own top-level security perimeter, its own dedicated master data, its own audit regime, structurally separate from everything else? Summer Camp doesn't — it's thin, it borrows Enrollment and has modest settings. Health Clinic does — highly sensitive independent data, a dedicated staff role, an audit standard stricter than anywhere else on the platform. Getting both of these questions right independently, rather than collapsing them into one, is exactly the distinction this is: Module on the enrollment axis, full Domain on the bounded-context axis.

### Purpose
Manage the medical wellbeing of students — and where applicable, staff — as the school's clinical system of record, from routine health data through emergency incident response. Isolated, operationally and legally, from the rest of the platform's data given its sensitivity.

### Responsibilities
Medical Records, Daily Clinic visits, Medication administration, Incident logging, Screenings, Chronic Health Monitoring, Parent Notification of health events.

### Business Capabilities
Record and retrieve a student's medical history at point of care · log a clinic visit with diagnosis and treatment · administer and track medication against guardian consent · capture an incident with first-aid response and severity assessment · run periodic health screenings · monitor chronic conditions over time · notify a guardian in real time on defined trigger events · produce health-trend reporting for the school's own operational awareness — never as a substitute for a qualified clinician's judgment.

### Submodules
Medical Records · Daily Clinic · Medication · Incidents · Screenings · Health Monitoring · Parent Notifications

### Master Data
**Student Medical Profile** (blood type, chronic conditions, allergies, immunization record — referenced by every Clinic Visit and Medication record) · **Medication Catalog** (a controlled reference list, referenced by every Medication Administration event, not free text) · **Health Condition / Allergy taxonomy** (a controlled vocabulary — screening and monitoring reports are only aggregable if this isn't free text).

### Settings
Parent-notification trigger rules (which events are immediate vs. daily-digest) · guardian-consent expiry period for standing medication administration · screening schedule/frequency by Grade · incident-severity classification thresholds (what counts as "hospital transfer required").

### Workflows
**Clinic Visit** — check-in → examination → diagnosis → treatment → optional hospital referral. **Medication Administration** — guardian consent obtained → scheduled dose → administered → logged. **Incident Response** — injury/accident → first aid → severity assessment → optional hospital transfer → guardian notification. **Screening Cycle** — scheduled → conducted → results recorded → out-of-range results routed to guardian/follow-up.

### Domain Events
`ClinicVisitRecorded` · `MedicationAdministered` · `IncidentReported` · `HospitalTransferInitiated` · `ScreeningResultRecorded` · `ChronicConditionFlagRaised`. These are what Parent Notifications, Reports, and anything else consume — nothing outside this domain reads its tables directly (see Data Ownership).

### Automation Opportunities
Guardian notification fires automatically the instant `IncidentReported`/`HospitalTransferInitiated`/`MedicationAdministered` occurs, no manual notify step · auto-flag a student for follow-up when a screening result falls outside a configured normal range · auto-remind clinic staff of a scheduled medication dose.

### AI Opportunities
Deliberately conservative, given the domain. Pattern detection across screening history (flag a trend — e.g., a consistently rising BMI — for human review, never an autonomous diagnosis) · triage assistance at check-in (suggest likely urgency from reported symptoms, always human-confirmed before it affects care) · anomaly detection across incident logs (unusually frequent visits from one student, worth a human look). Every one of these is decision-support for a qualified human, never an autonomous medical decision — that's the actual design constraint, not a caveat, and it should be enforced structurally: no AI output writes directly to a Medical Record without an explicit clinician confirmation step.

*(Not yet updated: this section predates [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md)'s unified `AIDecision` primitive and should reference it directly rather than describing its own ad-hoc "human must confirm" wording — tracked as an open item.)*

### Provider Slots
**Health Authority Reporting Provider** — varies entirely by country (a Ministry of Health API in one deployment, nothing in another); must be optional and swappable, never assumed, per the platform's own commercial-product design rule. **Insurance Provider** — claims/eligibility integration varies by insurer and by country. **Notification Provider** (SMS/Email/Push) — already-existing platform-wide slots, this domain just consumes them for its own trigger events. **AI Diagnosis/Triage-Assist Provider** — optional, decision-support only, off by default; medical-AI liability and regulation vary enormously by jurisdiction, so this must never ship enabled by default.

### Public APIs
A narrow, explicitly-scoped read API for Parent Notifications and the Student & Parent App's own health screen — never a general query API into raw medical records. An events feed (§ Domain Events) other domains can subscribe to without being granted any read access to the underlying data — subscribing to an event versus reading the record is the actual isolation mechanism here, not just a permission check on top of shared access.

### Extension Points
New Screening types (vision, hearing, height/weight/BMI today) addable as configuration, not code · new Provider categories as new national health-authority integrations are needed per deployment · custom incident-severity classification schemes per deployment's own policy.

### Mobile Features
**Parent** sees: child's medical profile (read-only), health notifications, health reports, medication log. **Health Clinic Staff** (a dedicated role, never general Employee access) sees: patient list, visit queue, medical record — scoped to their own clinical assignment, not the whole student body by default.

### Dashboards
Daily clinic visit volume · active incident status board · today's medication schedule · screening completion progress by Grade.

### Reports
Common conditions/illness trends · medical absence report · medication usage report · incident report (by type, severity, Branch) · immunization compliance rate.

### KPIs
Visit count · average response time (incident reported → first aid administered) · incident rate per enrolled student per period · immunization compliance percentage.

### Security Classification
**Highly Sensitive** — the platform's highest tier, above ordinary Confidential (financial data, employee records). Medical data about minors carries both heightened legal protection and heightened real-world consequence if mishandled.

### Permissions
- **Health Clinic Staff** — full clinical record access, scoped to their assigned patients/branch.
- **Treating Clinician** — same, narrowly.
- **Parent** — read-only, own child only, via the mobile app.
- **Teacher / general Employee** — no access by default; anything beyond "student is medically excused today" must be explicit, deliberate policy, never ambient visibility.
- **System Administrator** — access is itself logged and treated as exceptional, not the standing default it is for most other domains on this platform. Worth stating explicitly since it's a real deviation from how every other domain in this document treats System Admin.

### Audit Requirements
Full audit trail on every **read**, not only writes — who viewed a medical record matters as much as who changed it, unlike most domains where read access isn't separately audited. Access-anomaly alerting: a staff member viewing records outside their assigned patient list should be flagged, not just logged after the fact.

### Data Ownership
This domain owns 100% of the medical record. No other domain may store a copy of any part of it. Domains that need to *know something happened* — Parent Notifications sending a message, Attendance knowing a student is medically excused — consume this domain's events, never its tables. Isolation is enforced by that boundary, not by a permission flag on shared storage.

### Future Expansion
Telemedicine integration — a genuine future Provider Slot, same shape as LMS's Meeting Provider ("Remote Consultation Provider") · wearable-device monitoring integration for chronic conditions · regulatory reporting automation as more national Health Authority Providers get built out per deployment.

### Commercial Differentiators
- **Ministry/Health-Authority Provider abstraction** — most competing school ERPs are built for one country's health-reporting regime and can't expand past it without a rewrite; this platform can onboard a new country's requirement as a new Provider, not a new product.
- **Insurance Provider abstraction** — same reasoning, applied to claims/eligibility integration, a genuine procurement advantage for multi-country operators.
- **AI Decision Support, never autonomous diagnosis** — a real trust differentiator, not a hedge: schools and regulators are far more likely to approve AI-assisted triage than autonomous AI diagnosis, and this domain is designed to only ever offer the former.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) (correction pending)
- **Related Domains:** [Students](students.md), [School Operations](school-operations.md), [Smart Campus](smart-campus.md), [HR](hr.md) (Emergency Coordination).
