# Domain 13: Reception

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v3 · **Related ADRs:** [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md), [BUS-0016](../adr/business/0016-domain-folder-split-threshold.md) (Mail & Correspondence's future split-out threshold), [BUS-0020](../adr/business/0020-academic-learning-boundary.md) (same naming-collision-correction discipline, applied here to "Visitor"), [BUS-0022](../adr/business/0022-reception-domain-boundary.md), [BUS-0023](../adr/business/0023-reception-external-party-and-visit-lifecycle.md) (External Party, polymorphic correspondence routing, full Visit lifecycle) · **Related Domains:** [Smart Campus](smart-campus.md) (owns Visitor Access Credential/Access Events; consumes this domain's Visitor record — never the reverse), [HR](hr.md) (Employee/Department resolves "who is the host"), [Administration](administration.md) (permissions, Provider Registry), [Platform Services](platform-services.md) (Document Template engine for visitor badges, Media for correspondence archiving), [Students](students.md) (a Visit may reference a Student when its purpose concerns one)

**Design history.** This domain was documented only after a dedicated bounded-context review, run with the same discipline as Academic's: no file was written until the boundary with Smart Campus's already-shipped "Visitor Management" was resolved. The first-draft resolution ("Visitor ownership transfers from Smart Campus to Reception") was itself corrected before anything was written — the real issue was that "Visitor" had always named two distinct concepts (an administrative identity and a physical-access credential), the same class of collision already found and fixed for Program, Department, and Course. See BUS-0022 for the full resolution.

**Domain vs. Module, same reconciliation as Health Clinic, School Operations, Smart Campus, and Inventory**: no independent enrollment — Module-shaped on the Program axis, since Reception never issues its own enrollment/branding/portal. Full Domain on the bounded-context axis: independent Master Data (Visitor, Visit, Correspondence Item — none borrowed from another domain), its own security/audit perimeter (visitor and correspondence logs carry real accountability weight), and a genuinely distinct front-of-house process from every other domain in this document.

### Purpose
The platform's first administrative point of contact with anyone external to the institution — registering why a Visitor is here and who they've come to see, and managing the flow of incoming and outgoing correspondence and parcels between the outside world and internal departments. Deliberately owns no physical access-control mechanism of its own: Reception answers "who arrived and why"; [Smart Campus](smart-campus.md) answers "is this person authorized to pass this door" (BUS-0022).

### Responsibilities
Visitor Reception (registration, host notification, check-in/check-out), Mail & Correspondence (incoming/outgoing mail, parcels, inter-department routing, acknowledgment, archiving).

### Business Capabilities
Register a Visitor and log a Visit (purpose, host, expected duration) whether pre-registered or walk-in · notify the relevant Employee/Department the moment a Visitor arrives, via the platform's existing notification channels · issue an administrative Visitor badge/pass (a document, not an access credential) · check Visitors out and close the Visit record · log incoming and outgoing mail and parcels, including delivery method and correspondence type · route correspondence between internal departments with acknowledgment of receipt (paper or electronic) · archive correspondence for later retrieval · report on visit volume, visit purpose trends, and correspondence throughput.

### Submodules
Visitor Reception · Mail & Correspondence

### Master Data
**Visitor** (BUS-0022) — the administrative identity: who, purpose of visit, host, contact information, associated documents, a visit reference number. Deliberately not a lightweight Person record the way Applicant or Student are — a Visitor's relationship to the institution is inherently temporary. **Owned here outright**; Smart Campus consumes this record to bind an Access Credential, never the reverse.

**Visit** — the specific visit instance: entry/exit time, status (expected, checked-in, checked-out, no-show), which Reception Desk handled it.

**Reception Desk** — the physical desk/location that handled a Visit or a piece of correspondence; becomes meaningful once a campus has more than one entrance/building with its own front desk.

**Correspondence Item** — document/parcel description, Correspondence Type, Delivery Method, routing history, acknowledgment status. Sender and recipient are **polymorphic** (BUS-0023): an Employee or Department (HR), a future Domain/Business Unit reference (seam reserved, not yet needed), or an External Party — routing is never assumed to resolve to a single Employee.

**External Party** (BUS-0023, 🟡 Proposed) — an optional Master Data entity for correspondents that recur often (a Ministry, a shipping company, a bank, a supplier, a partner university, a government body): Type, Name, Contact Information. `Correspondence Item` may reference an External Party **or** fall back to free text for a one-off/unlisted sender — free text is never removed as an option, only offered an alternative to prevent the same real-world correspondent accumulating dozens of inconsistent name variants.

**Delivery Method** (manual hand-delivery, postal, courier, email) and **Correspondence Type** (incoming, outgoing, internal, parcel, document) — small reference lists, not full aggregates.

### Settings
Visitor badge validity duration default · required fields per visit purpose (a vendor visit may require different intake fields than a parent meeting) · badge/credential return verification required before check-out completes (BUS-0023) · correspondence acknowledgment requirement (which Correspondence Types require a signed/electronic acknowledgment before being considered received) · archival retention period for Correspondence Items (a placeholder default — a full retention policy is out of scope until Mail & Correspondence's possible future split, per BUS-0016).

### Workflows
**Full Visit lifecycle** (BUS-0023 — documented end-to-end, not just through arrival): Visit Created → Check-in → Host Notification → *(optional)* Access Credential issued by [Smart Campus](smart-campus.md), referencing this Visitor (BUS-0022) → Check-out → Visit Closed.

**Visitor Check-in** — pre-registration or walk-in → Visit logged → host notified → host approves (or Visitor is met directly at the desk) → Visitor badge issued.

**Visitor Check-out** (BUS-0023 — expanded from a single step) — covers: the escorting Employee's sign-off where a Visitor was accompanied rather than left unescorted; group-visit checkout (multiple Visitors closed against one Visit or linked Visits together, not one at a time); any equipment or items leaving campus with the Visitor, noted on the Visit record; verification that an issued badge (and, where one was issued, the Smart Campus Access Credential) has been returned or is confirmed revoked before the Visit is marked closed.

**Correspondence Intake** — item received → logged with Delivery Method/Correspondence Type → routed to the internal Employee/Department/Business Unit or External Party (BUS-0023) → acknowledgment captured (paper signature or electronic) → archived.

Where a Visit requires passing a controlled Access Point, Reception's Visitor record is hard-checked against by [Smart Campus](smart-campus.md), which issues and manages its own Access Credential referencing this Visitor — Reception never issues or manages that credential itself (BUS-0022).

### Domain Events
`VisitorRegistered` · `VisitorCheckedIn` · `VisitorCheckedOut` · `VisitorNoShow` · `CorrespondenceReceived` · `CorrespondenceRouted` · `CorrespondenceAcknowledged` · `CorrespondenceArchived`

### Automation Opportunities
Auto-notify the host the instant a Visitor checks in, with zero manual step · auto-flag a Visit as no-show if the expected Visitor never checks in by a configured window · auto-archive correspondence once acknowledgment is captured, with zero manual filing step.

### AI Opportunities
OCR-based auto-extraction of sender/recipient/document-type from a scanned piece of correspondence, proposed for human confirmation · suggested-host lookup from free-text visit purpose (e.g., "here to see the Grade 5 coordinator" resolves to a specific Employee). Routed through the unified `AIDecision` primitive (BUS-0003) from the start, the same discipline Academic's new documentation follows — this domain carries no retroactive-correction debt since it postdates BUS-0003.

### Provider Slots
None of its own — reuses Administration's existing Email/SMS/Push/WhatsApp Provider Slots (via Provider Registry) for host notification, and Platform Services' Document Template engine for the visitor badge, exactly as every other domain already does. No new Provider category is introduced by this domain.

### Public APIs
A Visitor read API, consumed by Smart Campus to bind an Access Credential (BUS-0022) · a host-notification trigger, riding on Administration's existing notification Provider Slots · a correspondence-status read API for the receiving department.

### Extension Points
Additional Correspondence Types and Delivery Methods, addable as configuration · additional External Party Types, addable as configuration (BUS-0023) · additional Reception Desks as campus footprint grows, each optionally scoped to a Zone (reusing School Operations'/Smart Campus's existing Zone concept rather than inventing a new location entity — 🔵 Deferred until a multi-desk campus actually needs it).

### Mobile Features
**Employee App**: front-desk staff check Visitors in/out, approve/deny visits as a host, view own pending visitor notifications; any employee can pre-register an expected Visitor. **Parent App**: pre-register a visitor for their own visit (a guest attending an event, for instance) — this moves here from Smart Campus's mobile surface (BUS-0022), since it's registering a Visit, not requesting a physical-access credential.

### Dashboards
Visitors currently checked in · today's expected visits · correspondence awaiting routing or acknowledgment.

### Reports
Visit log by date/purpose/host · visitor volume trend · correspondence throughput by type/delivery method · outstanding (unacknowledged) correspondence report.

### KPIs
Average visitor check-in time · no-show rate · correspondence acknowledgment turnaround time.

### Security Classification
**Sensitive** — visitor and correspondence records carry real privacy weight (who was on campus, what documents arrived) but this domain owns no biometric data and controls no physical device, so it sits below Smart Campus's Highly Sensitive tier.

### Permissions
- **Reception Manager** — full.
- **Front Desk Staff** — day-to-day Visit check-in/check-out and correspondence intake, no reporting/settings access.
- **Employee (as Host)** — approve/deny a visit addressed to them, read own pending notifications only.

### Audit Requirements
Full audit on every Visit (check-in, check-out, no-show) and every Correspondence Item's routing/acknowledgment chain — a correspondence audit trail (who received what, when, chain of custody) carries its own accountability weight distinct from a simple visitor log.

### Data Ownership
Owns Visitor, Visit, Reception Desk, Correspondence Item, and External Party (BUS-0023) outright. **Consumes** Employee/Department from HR (host resolution, internal correspondence routing) and Student from Students (where a Visit's purpose concerns a specific student). **Feeds** Smart Campus with the Visitor record Smart Campus consumes to bind an Access Credential (BUS-0022) — Reception never consumes anything back from Smart Campus's Access Credential/Access Event data.

### Future Expansion
Mail & Correspondence splitting into its own domain if it accumulates real retention-policy, legal-correspondence, or e-signature requirements (🔵 Deferred, per BUS-0016's threshold discipline — not before). Subscribing to the cross-cutting Emergency Coordination service, to surface which Visitors are currently on campus during an active emergency (🟡 Proposed during design, not yet decided — a genuine safety candidate, but not something to build against until explicitly accepted).

### Commercial Differentiators
- **Unified Front-of-House Experience** — one coherent domain for both visitor handling and correspondence, instead of the two disconnected systems (a paper visitor log plus an email inbox) most competing school-management products leave as manual processes entirely outside the ERP.
- **Clean Handoff to Physical Security** — the Visitor-identity/Access-Credential split (BUS-0022) means a school can adopt Reception's administrative workflow without necessarily deploying Smart Campus's physical-access hardware at all, and add the hardware layer later with zero redesign — a genuine incremental-adoption advantage over bundled "smart visitor" hardware products.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related ADRs:** [BUS-0003](../adr/business/0003-ai-decision-unified-platform-primitive.md) · [BUS-0016](../adr/business/0016-domain-folder-split-threshold.md) · [BUS-0020](../adr/business/0020-academic-learning-boundary.md) · [BUS-0022](../adr/business/0022-reception-domain-boundary.md)
- **Related Domains:** [Smart Campus](smart-campus.md), [HR](hr.md), [Administration](administration.md), [Platform Services](platform-services.md), [Students](students.md).
