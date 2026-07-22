# Domain 10: School Operations & Campus Automation

[← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)

**Template:** v3 · **Related ADRs:** none dedicated yet — the Emergency Coordination ownership correction below exists only as an inline note, not a formal ADR (tracked as an open item) · **Related Domains:** [Academic](academic.md) (consumes timetable/calendar, never owns it), [Smart Campus](smart-campus.md) (shares IoT Sensor Provider category, both subscribe to Emergency Coordination), [Health Clinic](health-clinic.md) / [HR](hr.md) / [Students](students.md) (subscribe to Emergency Coordination)

**Domain vs. Module, same reconciliation as Health Clinic**: this domain doesn't enroll anyone — Module-shaped on the Program axis. But it owns a genuinely independent bounded context (a physical device fleet, safety-critical emergency workflows, its own execution/audit trail) — full Domain on the bounded-context axis. Worth naming precisely why it's different from every domain before it: this is the platform's first **Operational Technology (OT)** domain. Every prior domain manages *information* about people, money, or academics. This one issues commands to physical devices in the real world — bells ring, speakers play, screens display, alarms sound — and that changes the failure-mode analysis in ways no IT domain has had to consider (see Workflows and Security Classification).

**Correction (originally recorded 2026-07-22, not yet a formal ADR).** `EmergencyActivated`/`EmergencyDeactivated` were originally modeled as this domain's own Domain Events. That was premature: designing Smart Campus & Physical Security surfaced that Physical Security, Health Clinic, HR, and Students all need to react to the same emergency, and none of them has any real reason to depend on the Bell system to know one is happening. Emergency Activation is promoted to its own cross-cutting **Core Platform Service — Emergency Coordination** — owned by no single business domain; School Operations, Smart Campus, Health Clinic, HR, and Students all subscribe to it, and the correctly-permissioned role in any of them may trigger it. Event names and behavior are unchanged; only ownership moves.

### Purpose
Govern the physical, real-time daily operation of the campus — bell schedules, public announcements, audio, emergency response, and digital signage — as the platform's Operational Technology layer.

### Responsibilities
Bell Management, Timetable Synchronization (consuming, never owning, Academic's timetable and calendar), Public Announcement System, Audio Management, Emergency Management, Digital Signage, Device Management.

### Business Capabilities
Define and schedule bell profiles per branch/program/grade/day-type · synchronize bell execution automatically with Academic's timetable and calendar · broadcast live or scheduled announcements zone-by-zone or campus-wide · manage and schedule audio playback (assembly, anthem, recitation, background) · trigger and coordinate emergency response across every connected device simultaneously · manage digital signage content · monitor the health and connectivity of every registered physical device · log the outcome of every automated and manual command, individually, per device.

### Submodules
Bell Management · Timetable Synchronization · Public Announcement System · Audio Management · Emergency Management · Digital Signage · Device Management

### Master Data
**Bell Profile** (a named schedule — Academic Day, Ramadan, Exam, Holiday — referenced by which days/branches use it) · **Calendar Exception** (a specific date or range overriding the normal profile assignment, referencing a Bell Profile) · **Device Registry entry** (every bell controller, PA endpoint, audio controller, signage display, IoT device — referenced by every command/execution log entry) · **Zone** (a physical device grouping — a building, floor, or set of classrooms — referenced by zone-based announcements and device assignment).

### Settings
Default Bell Profile per Branch/Program/Grade · manual-override authorization policy (who may override, for how long) · emergency-override behavior configuration (which channels get preempted, in what priority order) · scheduled-audio playlist assignment · digital-signage content refresh interval.

### Workflows
**Bell Execution** — scheduled trigger → resolve the active Bell Profile for today (Branch/Program/Grade, any Calendar Exception) → send ring command → log the result. **Manual Override** — administrator triggers → authorization check → command sent → logged, distinctly flagged as manual, not scheduled. **Announcement Broadcast** — live or scheduled → resolve target zone(s) → pause any currently-playing audio on those zones → deliver → resume prior audio state. **Emergency Response (reactive)** — this domain no longer *owns* emergency activation (see the Correction note above) — it *subscribes* to the cross-cutting Emergency Coordination service and reacts by preempting every other command in flight, every zone, executing the alarm/evacuation/lockdown audio and signage appropriate to whatever event Coordination broadcasts → logs every device's individual result, since knowing which device did *not* respond during a real emergency is itself critical safety information, not an afterthought.

This last workflow is the one genuinely hard design requirement this domain introduces: "override everything during emergencies" isn't a feature bullet, it's a **command priority model** — Emergency must structurally preempt Manual Override, which must preempt Scheduled Automation, which must preempt Background/Ambient — or a real emergency broadcast can get queued behind a scheduled bell or lose a contested audio channel. This needs to be an explicit priority hierarchy in the design, not an implied "also send this."

### Domain Events
`BellExecuted` · `BellExecutionFailed` · `AnnouncementBroadcast` · `DeviceWentOffline` · `DeviceRecovered` · `ManualOverrideTriggered`

`EmergencyActivated`/`EmergencyDeactivated` are **no longer this domain's own events** — they belong to the cross-cutting Emergency Coordination Core Platform Service (see the Correction note above); this domain subscribes to them rather than publishing them.

### Automation Opportunities
Fully automatic bell execution from the resolved schedule, zero manual step in the normal case · auto-pause background/scheduled audio the instant a live announcement starts, auto-resume after · auto-play morning assembly/anthem/recitation on a fixed daily schedule · single-trigger campus-wide emergency response, no per-device manual step · auto-alert operations staff the moment a device goes offline, before it's needed for something time-critical.

### AI Opportunities
Predictive device-failure detection from health-check/heartbeat history (flag a device trending toward failure before it fails when actually needed) · anomaly detection on execution logs (a bell that should have rung but didn't, surfaced proactively) · natural-language announcement drafting assistance. Explicitly **not** AI-triggered emergency activation — activation must always be a deliberate, authorized human action, never automated inference, given the consequence of a false trigger.

*(Not yet updated: this section predates BUS-0003's unified `AIDecision` primitive and should reference it directly — tracked as an open item alongside Health Clinic's and Smart Campus's own sections.)*

### Provider Slots
This domain generalizes cleanly onto the exact mechanism already proven for SMTP/OAuth/Firebase/R2 — every hardware category is a Provider Slot, none hardcoded to a vendor: **Bell Controller Provider** · **PA System Provider** · **Audio Player Provider** · **Digital Signage Provider** · **IoT/Sensor Provider**.

Worth stating precisely: these five share one underlying shape — receive a command (ring/announce/play/display), execute it on physical hardware, report status back — which is structurally the same problem the platform's existing `HealthCheckRunner`/`ProviderManager` pattern already solves for software integrations. This is the first place that pattern applies to hardware instead of APIs, and it should reuse the same health-check/connectivity-status mechanism rather than inventing a parallel one.

### Public APIs
A command API for triggering bells and announcements, tightly permissioned (emergency triggering itself is the cross-cutting Emergency Coordination service's API, not this domain's) · a device-status read API for the monitoring dashboard and for other domains that might need to know "is the PA system currently in emergency mode" without being granted control over it · an events feed (§ Domain Events).

### Extension Points
New Bell Profile types beyond Academic/Ramadan/Exam/Holiday, addable as configuration · new Zone definitions as campus layout changes · new device categories, each a new Provider Slot following the same shape as the five above, no redesign required.

### Mobile Features
**Administrator / Operations Staff**: trigger bells manually, broadcast announcements, activate emergency mode, control playback remotely, monitor connected device status. Deliberately **no** Student or Parent App presence — nothing in this domain belongs on those surfaces.

### Dashboards
Live device connectivity map · today's resolved bell schedule and execution status · active emergency status board · current audio/PA channel state per zone.

### Reports
Bell execution history · device health trend · failed schedule report · announcement history · emergency drill report — drills are a distinct, loggable event type from real activations, so drill history never pollutes real-incident reporting.

### KPIs
Bell execution success rate · device availability (uptime %) · audio system uptime · emergency response time (activation trigger → full campus coverage confirmed).

### Security Classification
**Highly Sensitive by action-criticality, not data-sensitivity** — a distinction worth drawing explicitly against Health Clinic's own top-tier classification: this domain's data (schedules, device status) isn't personally sensitive, but its *actions* have direct physical/safety consequences (a false lockdown trigger, a failed evacuation alarm). Two different structural reasons land at the same top tier. Emergency-activation authority is its own narrow permission, never bundled into ordinary operations authority.

### Permissions
- **Operations Manager** — full, including Bell Profile and Device management.
- **Operations Staff** — day-to-day trigger/broadcast/playback control, no schedule or device configuration.
- **Emergency Activator** — now a cross-cutting permission on the Emergency Coordination service itself, not a School Operations-scoped role; this domain reacts to it rather than granting it. Kept deliberately separate from Operations Manager either way — the blast radius of a wrong emergency trigger warrants its own gate regardless of which domain enforces it.
- **System Administrator** — device/Provider credential management only, not day-to-day operational triggers.

### Audit Requirements
Full audit trail on every command, scheduled or manual — who/what triggered it, which devices executed it, whether each one individually succeeded. Emergency activations need an audit record complete enough to reconstruct exactly what happened, device by device, after the fact. This is the one domain on the platform where an incomplete audit trail is itself a safety failure, not just a compliance gap.

### Data Ownership
Owns its Device Registry, Bell Profiles, and execution logs outright. **Consumes, never owns,** Academic's timetable and calendar (Academic Year, terms, holidays) — Timetable Synchronization reads from Academic, it never duplicates or overrides Academic's own calendar data. **No longer owns** Emergency Activation itself (moved to the cross-cutting Emergency Coordination Core Platform Service — see the Correction note above); this domain owns only its own reaction to it.

### Future Expansion
Smart Campus integration — Access Control (door/gate systems), CCTV, general IoT sensors (temperature, occupancy, air quality), Smart Classroom systems, Energy Management (HVAC/lighting automation) — now realized as its own domain, [Smart Campus & Physical Security](smart-campus.md), rather than a hypothetical. Every one of these was architecturally the same shape as the five Provider Slots already defined here — a new hardware category, a new Provider Slot, no redesign — which is exactly why this domain was worth building with that generality from day one rather than narrowly as "just the bell system."

### Commercial Differentiators
- **Hardware Abstraction** — most competing "smart bell" systems are single-vendor hardware bundles; Provider Slot discipline here means a school isn't locked into one supplier for the contract's lifetime.
- **Smart Campus Ready** — the extension points already defined here meant Smart Campus & Physical Security could be added as a genuine sibling rather than a redesign, a real proof of the platform's own extensibility claim, not just a stated intention.
- **Vendor Independent** — the buyer-facing version of Hardware Abstraction: a competitive procurement position against single-vendor "smart school" bundles that dominate this market today.

---

## Navigation

- [← Back to Business Blueprint](../BUSINESS_BLUEPRINT.md)
- **Related Domains:** [Academic](academic.md), [Smart Campus](smart-campus.md), [Health Clinic](health-clinic.md), [HR](hr.md), [Students](students.md).
