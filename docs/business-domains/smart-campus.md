# Domain 11: Smart Campus & Physical Security

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v3 · **Related ADRs:** [BUS-0022](../adr/business/0022-reception-domain-boundary.md) (Visitor identity is owned by Reception; this domain owns only the Visitor Access Credential and Access Events, and consumes Reception's Visitor record to issue one) — Emergency Coordination itself still shares the same unresolved ADR gap as School Operations · **Related Domains:** [Reception](reception.md) (owns Visitor identity; this domain consumes it to bind an Access Credential — never the reverse, BUS-0022), [School Operations](school-operations.md) (peer OT domain, shared IoT Sensor Provider, both subscribe to Emergency Coordination), [Students](students.md) / [HR](hr.md) (Attendance consumes access events), [Academic](academic.md) (timetable for smart-classroom automation), [Health Clinic](health-clinic.md) (subscribes to Emergency Coordination)

**Domain vs. Module, same reconciliation as Health Clinic and School Operations**: no independent enrollment — Module-shaped on the Program axis. Full Domain on the bounded-context axis, for a third distinct reason this time: independent, highly sensitive biometric data (face, fingerprint); an independent device fleet distinct from School Operations'; a legally significant CCTV retention obligation of its own.

**Distinct from School Operations, not a submodule of it, for a precise reason**: School Operations governs *scheduled/broadcast* operations with no per-person identity check involved — a bell doesn't know who's in the room. This domain governs *identity-gated physical access* — a door needs to know who's requesting entry and whether they're authorized. That's a fundamentally different problem, tied to Identity/People rather than to scheduling, and it's why the two don't collapse into one domain even though both are Operational Technology.

### Purpose
Govern who and what may physically move through the campus — doors, gates, parking — and provide the surveillance layer (CCTV) that secures it. The platform's identity-gated physical-security layer, distinct from School Operations' broadcast/scheduling layer.

### Responsibilities
Access Control (Student, Employee, Visitor, and Parking gates), Visitor Access Control, CCTV Integration, Smart Classroom device control, IoT Sensors, Parking, and participation in coordinated emergency response.

### Business Capabilities
Authenticate a person at a physical checkpoint via card, QR, face, or fingerprint and grant or deny passage · issue and manage a time-bounded, revocable Access Credential for a Visitor already registered in [Reception](reception.md) (this domain never registers a Visitor itself — BUS-0022) · maintain a security blacklist · surface CCTV footage against logged incidents from any domain for after-the-fact investigation · control smart-classroom environment (board, projector, lighting, AC) and capture automatic attendance from access events · monitor environmental IoT sensors and raise alerts on anomaly · manage parking gate access and permit assignment · participate in coordinated emergency response with the physical action correct for the emergency *type* — evacuation and lockdown are opposite responses, not variations of one "emergency mode."

### Submodules
Access Control · Visitor Access Control · CCTV Integration · Smart Classroom · IoT Sensors · Parking

### Master Data
**Access Credential** (a card/QR/biometric template bound to a Person — Student or Employee — or, for a Visitor, bound to the Visitor record owned by [Reception](reception.md), never a Visitor identity of this domain's own — BUS-0022; referenced by every access event) · **Access Point** (a specific door/gate/barrier, referenced by access events and device health monitoring) · **Security Blacklist entry** · **Parking Permit** (bound to a vehicle/Person, referenced by parking gate events).

### Settings
Default access hours per Access Point/role · visitor pass validity duration · blacklist review/expiry policy · CCTV retention period — this varies enormously by jurisdiction and must be a per-deployment setting, never a hardcoded default, per the platform's own commercial-product standing rule · smart-classroom automation rules (auto-attendance-from-access-event enabled or not, per Branch).

### Workflows
**Access Grant/Deny** — credential presented → identity and authorization resolved → gate/door actuated → logged. **Visitor Access Credential Issuance** — a Visitor is already registered and checked in via [Reception](reception.md) → if the Visit requires passing a controlled Access Point, this domain issues a time-bounded, revocable Access Credential referencing Reception's Visitor record → automatic expiry, synced to Reception's visit duration. This domain never performs visitor registration, host approval, or badge printing itself — that's Reception's workflow entirely (BUS-0022). **CCTV-Incident Linking** — an incident logged by any domain, tagged with time/location, surfaces the relevant footage for review, without granting the requesting domain any general CCTV access. **Emergency Response** — subscribes to the cross-cutting Emergency Coordination Core Platform Service (see School Operations' Correction note) and reacts with the response correct for the emergency *type*: Fire/Evacuation unlocks every controlled door on the egress path; Lockdown/Intruder locks every controlled door and suppresses normal visitor/parking access. Getting this distinction wrong — locking doors during a fire — isn't a hypothetical edge case, it's exactly the mistake a "just override everything" design produces, which is why emergency type has to be a first-class part of the response, not an afterthought.

### Domain Events
`AccessGranted` · `AccessDenied` · `VisitorAccessCredentialIssued` · `VisitorAccessCredentialExpired` · `BlacklistMatchDetected` · `DeviceWentOffline` · `DeviceRecovered` · `EnvironmentalAlertRaised`

`VisitorRegistered` is **not this domain's event** — visitor registration belongs to [Reception](reception.md) (BUS-0022); this domain only issues and expires the Access Credential that references Reception's already-registered Visitor.

### Automation Opportunities
Automatic attendance capture from access events — a student badging in is also an attendance record, *consumed by*, never *owned by*, Students/Attendance · auto-expire visitor passes with zero manual step · auto-alert on blacklist match at any Access Point · auto-adjust smart-classroom environment based on scheduled occupancy from Academic's timetable.

### AI Opportunities
Face-recognition matching itself is table-stakes for this category, not a differentiator · anomaly detection on access patterns (a credential used at an unusual hour/location, worth a human look) · crowd/occupancy estimation from CCTV for safety monitoring, not identification. Explicitly conservative in the same spirit as Health Clinic's AI stance: identity-verification decisions can be AI-*assisted*, but the underlying credential match must stay deterministic and auditable, not a black-box inference, given the consequence of a false grant or false deny.

*(Not yet updated: this section predates BUS-0003's unified `AIDecision` primitive and should reference it directly — tracked as an open item alongside Health Clinic's and School Operations' own sections.)*

### Provider Slots
**Access Control Provider** (card/door-controller hardware) · **Biometric Provider** (face recognition, fingerprint — vendor-swappable, never assumed) · **CCTV Provider** · **Visitor Management Provider** (kiosk/QR hardware) · **Parking Gate Provider** · **IoT Sensor Provider** — deliberately the *same* slot category School Operations already defines, reused rather than duplicated, since a temperature or smoke sensor is the same kind of device regardless of which domain consumes its readings.

### Public APIs
An access-event feed other domains subscribe to (Students/Attendance, HR/Attendance) without gaining any control over physical devices · CCTV footage retrieval scoped strictly to incident-linked requests, never general browsing access via API. (Visitor status itself is read from [Reception](reception.md)'s own API, not duplicated here — BUS-0022.)

### Extension Points
New Access Control hardware categories, new biometric modalities, new CCTV analytics providers (license-plate recognition for Parking, for instance) — all new Provider Slot instances, no redesign.

### Mobile Features
**Employee / Security Staff**: blacklist management, live access-event monitoring, CCTV-incident linking. **Student**: none directly — access credentials are provisioned, not self-managed.

Visitor pre-registration and the visitor's own pass now live on [Reception](reception.md)'s mobile surface, not here (BUS-0022) — this domain's Parent-facing surface is limited to their own standing Access Credential (daily pickup), never a per-visit registration flow.

### Dashboards
Live access-event feed by Access Point · visitors currently holding an active Access Credential (distinct from Reception's "visitors currently checked in" — a Visit doesn't always require a credential) · device connectivity map · parking occupancy.

### Reports
Access history by Person/Access Point · visitor Access Credential usage log (distinct from Reception's own Visit log — this one tracks credential/gate usage, not the visit record itself) · blacklist incident report · device health · parking utilization.

### KPIs
Access grant success rate · average visitor Access Credential issuance time (distinct from Reception's own "average visitor check-in time" KPI — this measures credential turnaround, not front-desk processing) · device uptime · false-reject rate — a genuine security-usability metric, since too many false rejects trains people to prop doors open, which defeats the whole domain's purpose.

### Security Classification
**Highly Sensitive**, for a third distinct reason from the other two OT/sensitive domains in this document so far: biometric identifiers are the platform's most legally protected data category in most jurisdictions, and unlike a password, a compromised biometric template can't be reset. CCTV footage carries its own separate, serious privacy/retention obligation. This domain carries the platform's single highest data-protection bar — above even Health Clinic's medical records in several regulatory regimes.

### Permissions
- **Security Manager** — full.
- **Security Staff** — day-to-day access-credential and device-monitoring operations, no device or credential *configuration*. Visitor registration itself is not this domain's permission to grant — that's Reception's **Front Desk Staff** role (BUS-0022); this domain has no "Front Desk" permission of its own.
- **Emergency Activator** — the same cross-cutting permission shared with School Operations and the Emergency Coordination service, not domain-specific.
- **System Administrator** — device/Provider credential management only, access itself logged and exceptional, the same standing established for Health Clinic.

### Audit Requirements
Full audit on every access grant *and* deny — a denied entry is as important to the record as a granted one. CCTV access itself must be audited (who viewed which footage, when, and why) — the same "read access is itself sensitive" principle established for Health Clinic applies here with equal or greater force. Biometric-template enrollment and deletion audited without exception.

### Data Ownership
Owns Access Credentials, Access Points, and CCTV access metadata outright (footage storage itself may live in Media/a Storage Provider, but this domain owns the record of who accessed what, when). Consumes Person identity from People, Visitor identity from [Reception](reception.md) (BUS-0022 — never owned here), and Academic's timetable for smart-classroom automation. Never duplicates biometric templates into any other domain's storage.

### Future Expansion
License-plate recognition for Parking · integration with municipal/government security systems where required by regulation — a genuine future Provider Slot, never assumed · full device-health convergence with School Operations' own fleet under one unified monitoring dashboard, while the two domains' business ownership stays separate.

### Commercial Differentiators
- **Vendor-Independent Physical Security** — most competing "smart campus" security offerings are single-vendor hardware bundles (one badge system, one CCTV brand); Provider Slot discipline here means a school keeps its existing hardware investment or shops competitively, a genuine procurement advantage over locked-in competitors.
- **Emergency-Type-Aware Response** — coordinated, type-specific reaction (evacuation unlocks, lockdown locks) rather than a single undifferentiated "emergency mode" is a real safety differentiator few competing systems get right.
- **Cross-Domain Incident-CCTV Linking** — footage surfaced automatically wherever an incident is logged (Health Clinic, School Operations, this domain's own access events), without granting general surveillance access — a genuine investigative capability most siloed security products can't offer because they don't share an event bus with the rest of the school's operational data.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [School Operations](school-operations.md), [Students](students.md), [HR](hr.md), [Academic](academic.md), [Health Clinic](health-clinic.md).
