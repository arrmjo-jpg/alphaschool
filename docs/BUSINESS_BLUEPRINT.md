# AlphaSchool ERP — Business Blueprint (Master Index)

**Status: Phase 1 of a phased domain discovery — not architecture, not implementation.**

**Refactored 2026-07-22** into a modular structure: this document is now the master index only. Every domain's full specification lives in its own file under `docs/business-domains/`. Nothing below is a summary or excerpt — each domain document is complete and self-contained; this file exists to navigate between them, track cross-cutting decisions, and hold the governance apparatus that spans all of them.

## Overview

This is business/product domain discovery — purpose, responsibilities, workflows, reports, mobile features, integrations, permissions, per domain — produced before any implementation begins. It is deliberately separate from `docs/DOMAIN_BLUEPRINT.md`, which is the frozen *technical* backend architecture and is law; this document doesn't touch or redesign anything frozen there. Where this document's business analysis and the existing frozen architecture already agree (e.g., Enrollment's state machine, the Number Generator pattern, the Provider Registry), that's stated as confirmation, not re-derivation — this document builds on decisions already made, it doesn't relitigate them.

### Vocabulary and mechanisms reused throughout

- **Master Data vs. Configuration**: if another table references it by ID, it's Master Data, not a Setting (see the classification algorithm below).
- **Program vs. Module**: an offering with its own enrollment/branding/portal (School, Kindergarten) is a Program; a capability that operates on students already enrolled elsewhere (Summer Camp, Scout Camp) is a Module.
- **Provider Model**: every external integration is modeled as a named capability with swappable providers — `DeclaresProviderSlots`, already built and proven (SMTP, Google OAuth, Firebase, R2 Storage are real, shipped Providers today) — not a hardcoded vendor.
- **Approval Engine, Audit, Number Generator, Document Engine, Media**: already-built or already-designed Core/Foundation services every domain draws on rather than reimplementing.
- **People**: the frozen People/User split from `docs/DOMAIN_BLUEPRINT.md` (technical architecture, not re-documented here) — the authoritative source of Person identity that Inventory, Smart Campus, and any other domain consuming "Person identity" reference; not itself one of this document's 13 business domains.

## Documentation standards

### Classification algorithm (used throughout every domain document)

1. Does it produce an immediate side effect rather than change a rule for later? → **Action**, not configuration.
2. Does it have identity, referenced by other records via FK? → **Master Data**, not configuration.
3. Scoped to one user, no approval ceremony? → **User Preference**.
4. Governs security/access/operations, independent of which modules exist? → **Administration**.
5. Exactly one deployment-wide value, no single domain owns its meaning? → **Platform Configuration**.
6. Otherwise → **Domain Configuration**, owned by the one domain it belongs to.

### Domain document template — canonical (supersedes v1/v2/v3)

**Historical note.** This section previously described three incremental template versions (v1, v2, v3-plus-Commercial-Differentiators). A later architecture review — triggered by comparing Academic's and Learning's independently-evolved Blueprint structures — found the two had accidentally diverged into two different, undocumented shapes: Academic followed this document's own v1→v2→v3 line; Learning was written under a separate, later Enterprise-Architecture-review exercise that never inherited it. Traced to two different exercises run at different points, never reconciled — not a deliberate two-profile design. This section replaces v1/v2/v3 with one canonical model. v1/v2/v3 are retired, not deleted from history — every already-shipped domain document still names which of them it was written under, and that record stays as-is until migrated.

**Governing principle: every canonical question must receive an explicit decision — not every Blueprint must contain every section as its own heading.** A section may be merged into a neighboring one, or given an explicit "Not Applicable" disposition, when a domain genuinely has nothing to say for it — a small, narrow domain (Audit Log, Number Generator, Currency, an Identity-adjacent Foundation concern) shouldn't be forced into a template full of empty headings. The disposition itself must always be explicit and traceable, though — a section silently missing is an unexplained gap; a one-line "N/A — see §X" is a documented decision. **One exception, with no merge/N/A escape hatch: Commercial Differentiators.** It is a binding `CLAUDE.md` standing rule, not a section like the others — even a small Foundation-layer domain has to answer it with real content, however modest (e.g., "configurable per-domain numbering patterns, no vendor lock-in on ID formats" is a legitimate answer for something as small as Number Generator).

**Canonical sections, organized by responsibility, not by the order each was historically added:**

**I. Foundation** — 1. Domain Purpose · 2. Business Scope (absorbs the earlier Responsibilities/Business Capabilities split) · 3. Business Objects (classified as Master Data / Reference Data / Transaction / Configuration / Policy, per the classification algorithm above — absorbs Submodules as an internal grouping, not a separate section) · 4. Business Processes (absorbs Workflows; Trigger/Steps/Result/Business Rules shape) · 5. Boundaries

**II. Configuration & Behavior** — 6. Configuration (Business-Rule-vs-Configuration distinction enforced — a Policy-classified Business Object per §3 is never documented here) · 7. Events · 8. Automation Opportunities · 9. AI Opportunities (routes through the unified `AIDecision` primitive, BUS-0003)

**III. Platform Integration** — 10. Integrations (absorbs Provider Slots and Public APIs) · 11. Extension Points

**IV. Experience & Operations** — 12. Permissions · 13. Mobile Features · 14. Dashboards · 15. Reports · 16. KPIs

**V. Governance & Compliance** — 17. Security Classification · 18. Audit Requirements · 19. Domain Invariants · 20. Domain Contracts (absorbs and supersedes the earlier standalone Data Ownership section — "Owns" carries that role now)

**VI. Strategic** — 21. Future Expansion · 22. Commercial Differentiators (binding, no merge/N/A exception — see above)

**VII. Verification** — 23. Architecture Validation · 24. Verdict

**Binding from this point forward.** Every Blueprint written from here on — new domain or retrofit — follows this canonical template exclusively. There is no v1/v2/v3 choice to make; this is the only template.

### Migration Guidelines (defined now; execution is separate, not run by defining this)

1. **Academic** — smallest migration: add Verdict (the only canonical section missing after its recent Domain Invariants/Domain Contracts/Architecture Validation retrofit); reorganize existing sections into the seven clusters above (reordering only, no content change).
2. **Learning** — the larger migration: restore Mobile Features and Reports (both present in Learning's pre-retrofit file, dropped during its rewrite); add Commercial Differentiators (closing a binding-rule gap, not optional); add Automation Opportunities, AI Opportunities, explicit Provider Slots (Meeting Provider, LMS-Content Sync Provider — both named in Learning's own pre-retrofit file), Extension Points, Dashboards, KPIs, Security Classification, Audit Requirements; fold Data Ownership content into its already-present Domain Contracts section.
3. **Remaining domains** — Health Clinic, School Operations, Smart Campus, Inventory, Reception (already on the retired v3) need the same lightweight addition Academic just received: Domain Invariants, Domain Contracts, Architecture Validation, Verdict, plus reordering into the seven clusters. Administration, Platform Services, Students, Admissions, HR, Accounting (still on the retired v1) need the full canonical build-out. Sequencing follows the already-agreed retrofit priority (HR → Students → Admissions, Learning now already in progress), reordered only if a future decision says otherwise.

**Freeze order, following directly from this reconciliation:** governance unification (this section) → template adoption (this section, done) → domain migration (per-domain, not yet started) → freeze. Not the reverse — freezing a domain against a template that was still being reconciled would freeze the accidental gap along with it.

### File structure per domain (BUS-0016)

Every domain is a single file under `docs/business-domains/` until it actually needs otherwise. **A domain is promoted to its own folder only when its file exceeds roughly 250–300 lines** — not preemptively, not uniformly, per BUS-0016. When that happens, sections map onto the canonical template's seven clusters rather than a new taxonomy: `readme.md` (Domain Purpose/Business Scope/Commercial Differentiators), `entities.md` (Business Objects), `workflows.md` (Business Processes/Events/Automation Opportunities), `ai.md` (AI Opportunities — only for domains with real AI design weight), `integrations.md` (Integrations/Extension Points), `experience.md` (Permissions/Mobile Features/Dashboards/Reports/KPIs), `governance.md` (Security Classification/Audit Requirements/Domain Invariants/Domain Contracts), `decisions.md` (Related ADRs + Architecture Validation + Verdict + open items), `diagrams/`. Learning is the domain most likely to cross this threshold first, at its canonical-template migration.

### Architecture Status legend

Every entity, section, and decision should carry one of these where its status isn't obvious from context:

| Status | Meaning |
|---|---|
| 🟢 Accepted | Finalized — safe to build against without re-litigating |
| 🟡 Proposed | A live suggestion, not yet decided |
| 🔵 Deferred | Postponed on purpose, with the architectural seam already reserved |
| 🔴 Rejected | Considered and declined, with the reason recorded, not silently dropped |
| ⚪ Research Required | Needs investigation before it can be decided at all |

### Governance

This document is documentation-first. Conversation is temporary; this index, the domain documents, and the ADRs are the source of truth. No accepted architectural decision may exist only in chat history — see `CLAUDE.md`'s standing rule of the same name for the full binding process.

## Domain Map

**The Template column below still shows the retired v1/v2/v3 labels for most domains** — it reflects each one's state *before* the canonical-template reconciliation (Documentation standards, above) and will be updated domain-by-domain as each one actually migrates, per the Migration Guidelines. **Learning is the one exception** — its row already reflects partial migration (Domain Invariants, Domain Contracts, and Architecture Validation added); every other domain's label below is still fully pre-migration.

| # | Domain | Template | Document |
|---|---|---|---|
| 1 | Administration | v1 | [administration.md](business-domains/administration.md) |
| 2 | Platform Services | v1 | [platform-services.md](business-domains/platform-services.md) |
| 3 | Academic | Reference Blueprint | [academic.md](business-domains/academic.md) |
| 4 | Students | Reference Blueprint (certified & frozen 2026-07-27; Dashboards a documented exception, see students.md Verdict) | [students.md](business-domains/students.md) |
| 5 | Admissions | v1 | [admissions.md](business-domains/admissions.md) |
| 6 | HR | Reference Blueprint (certified & frozen 2026-07-27) | [hr.md](business-domains/hr.md) |
| 7 | Accounting | v1 | [accounting.md](business-domains/accounting.md) |
| 8 | LMS (Distance Learning) — rename to Learning Intelligence Platform pending (BUS-0007) | Reference Blueprint | [learning.md](business-domains/learning.md) |
| 9 | School Health Clinic | v3 | [health-clinic.md](business-domains/health-clinic.md) |
| 10 | School Operations & Campus Automation | v3 | [school-operations.md](business-domains/school-operations.md) |
| 11 | Smart Campus & Physical Security | v3 | [smart-campus.md](business-domains/smart-campus.md) |
| 12 | Inventory | v3 | [inventory.md](business-domains/inventory.md) |
| 13 | Reception | v3 | [reception.md](business-domains/reception.md) |

**Remaining, not yet documented**: Transportation, Library, Procurement, Assets, Facilities (ownership + boundary decided — see [BUS-0033](adr/business/0033-facilities-domain-room-ownership.md), 🟢 Accepted; the domain document itself is still unwritten, deliberately Designed-Not-Yet-Scheduled), Communications, Parents, Alumni, Activities, Events, Clubs, Summer Camp, Scout Camp, Compliance, Reports, Analytics, Examinations, Discipline, Special Education, Fundraising, Scholarships. Reception was added out of this list, by explicit request, ahead of the retrofit-priority queue below — it's a new domain, not a v1→v3 retrofit, so it doesn't reorder that queue.

**Reference Blueprints (as of 2026-07-27): Academic, Learning, HR, Students.** Students completed the full arc — Discovery, Architecture Reviews (Guardian Ownership, Learning Eligibility Boundary), ADR Sprint (BUS-0030, BUS-0031), Blueprint Integration, Reference Blueprint Completion, Freeze Preparation, Certification — the same rigor Academic, Learning, and HR were certified against, chosen deliberately as the domain to build next specifically because it depends on all three at once, making it the strongest available integration test of the baseline. Certified and frozen 2026-07-27 (see students.md Verdict; Dashboards carries a documented, non-blocking exception). All four now form a stable dependency baseline for downstream domains.

**Retrofit priority (agreed 2026-07-22, updated as domains complete their own arcs).** **Admissions** is next — it feeds Students, which is now a certified Reference Blueprint it can build against. Administration, Platform Services, and Accounting remain v1 and follow after Admissions. This ordering, not just the decision to retrofit, is the thing being recorded here — so it isn't re-derived or silently reordered in a future session.

**Cross-cutting corrections not yet owned by any single domain**: Emergency Coordination (a Core Platform Service, promoted out of School Operations — see [school-operations.md](business-domains/school-operations.md)'s Correction note; no formal ADR yet, tracked below); Event Stream (named in BUS-0005, not yet formally specified as its own Core Platform Service); the Privacy/Consent domain BUS-0006 depends on (doesn't exist yet).

## Dependency Map

| Domain | Consumes from | Feeds into |
|---|---|---|
| Administration | — (depends on nothing) | Every domain (permissions, Provider Registry credentials) |
| Platform Services | — | Accounting, Academic, HR, Reception (Document Templates for visitor badges, Media for correspondence archiving) |
| Academic | HR (Employee/Position for Teacher/Coordinator Assignment; Department for Academic Department cross-reference) | Students, Accounting, Learning, School Operations, Inventory, HR (Subject Offering feed for teacher-assignment workflows) |
| Students | Academic, Admissions (handoff) | Accounting, Transportation, Library, Learning (Enrollment/Person, gates Participation per BUS-0031), Health Clinic, Smart Campus, Reception (a Visit may reference a Student), Parents/Family (future — referenced for guardian_student, BUS-0030) |
| Admissions | — | Students (handoff on acceptance) |
| HR | Academic (Subject Offering feed for teacher-assignment workflows), Platform Services (Document Templates for Contract generation/versioning, BUS-0027) | Payroll (future), Academic (teacher assignments), Learning (Instructor/TA staffing, BUS-0025), Reception (Employee/Department for host resolution) |
| Accounting | Academic, Students | — |
| Learning | Academic, Students | Academic's gradebook (grades flow in, Academic owns the rule) |
| Health Clinic | Students | Students/Attendance (medical-excuse flag via events, never direct table access) |
| School Operations | Academic (timetable/calendar, read-only) | Emergency Coordination (co-owner with Smart Campus) |
| Smart Campus | People, Academic (timetable), Reception (Visitor identity, consumed to bind an Access Credential — BUS-0022) | Students/HR Attendance (access events), Emergency Coordination |
| Inventory | People, Procurement (future) | Accounting (billable issues, valued journal entries), Assets (future, cross-reference not merge) |
| Reception | HR (Employee/Department for host resolution), Students (a Visit may reference a Student), Administration, Platform Services | Smart Campus (Visitor identity, consumed to bind an Access Credential — BUS-0022) |

## Decision Log

Full ADRs live in `docs/adr/business/` (template: `docs/adr/business/template.md`), a separate track from `docs/adr/`'s numbered backend ADRs.

| ID | Title | Status | Domains |
|---|---|---|---|
| [BUS-0001](adr/business/0001-course-template-versioning.md) | Course Template requires explicit versioning; Offerings pin to a version | 🟢 Accepted | Learning |
| [BUS-0002](adr/business/0002-continuous-mastery-advisory-only.md) | Continuous Mastery is advisory only, never auto-derives Official Grade | 🟢 Accepted | Learning |
| [BUS-0003](adr/business/0003-ai-decision-unified-platform-primitive.md) | Reasoning Trace / AI Provider Version / Human Override unify into one AI Platform primitive | 🟢 Accepted | Learning, Health Clinic, Smart Campus |
| [BUS-0004](adr/business/0004-concept-graph-phased-adoption.md) | Concept Graph: seam now, phased adoption, not a v1 requirement | 🔵 Deferred | Learning |
| [BUS-0005](adr/business/0005-event-stream-core-platform-service.md) | Event Stream is shared transport (Core Platform Service), not Learning-owned | 🟢 Accepted | Learning, all event-emitting domains |
| [BUS-0006](adr/business/0006-ai-consent-deferred-to-privacy-domain.md) | AI consent belongs to a not-yet-built Privacy/Consent domain | 🔵 Deferred | Learning, Privacy (unbuilt) |
| [BUS-0007](adr/business/0007-learning-domain-renamed-learning-intelligence-platform.md) | Domain renamed LMS → Learning Ecosystem → Learning Intelligence Platform | 🟡 Proposed | Learning |
| [BUS-0008](adr/business/0008-inventory-classification-business-purpose.md) | Inventory's top-level classification is business purpose, not return status or tracking granularity | 🟢 Accepted | Inventory |
| [BUS-0009](adr/business/0009-tracking-strategy-setting-not-classification.md) | Tracking Strategy is a Setting with an Item Catalog override, never a classification input | 🟢 Accepted | Inventory |
| [BUS-0010](adr/business/0010-stock-movement-journal-entry-equivalent.md) | Stock Movement plays Journal Entry's architectural role inside Inventory | 🟢 Accepted | Inventory, Accounting |
| [BUS-0011](adr/business/0011-course-offering-base-structure-accepted.md) | Course Template/Offering/Staff/Sessions base structure accepted, three sub-issues remain open | 🟢 Accepted (base) | Learning |
| [BUS-0012](adr/business/0012-competency-framework-proposed.md) | Competency / Skill Framework | 🟡 Proposed | Learning, HR |
| [BUS-0013](adr/business/0013-rubrics-proposed.md) | Rubrics | 🟡 Proposed | Learning |
| [BUS-0014](adr/business/0014-content-authoring-workflow-proposed.md) | Content Authoring Workflow | 🟡 Proposed | Learning |
| [BUS-0015](adr/business/0015-learning-object-repository-proposed.md) | Learning Object Repository | 🟡 Proposed | Learning |
| [BUS-0016](adr/business/0016-domain-folder-split-threshold.md) | Domains split into folders only past a ~250–300 line threshold, not universally up front | 🟢 Accepted | All (documentation architecture) |
| [BUS-0017](adr/business/0017-academic-organizational-structure.md) | Faculty/Academic Department/Stage/Curriculum Path/Curriculum Specification — ownership, naming, versioning | 🟢 Accepted | Academic, HR, Students |
| [BUS-0018](adr/business/0018-academic-subject-model.md) | Subject/Subject Offering/Subject Version/Equivalency/Electives unified, "Course" reserved for Learning | 🟢 Accepted | Academic, Learning, Students, HR |
| [BUS-0019](adr/business/0019-academic-assignment-model.md) | Teacher/Homeroom/Coordinator assignment reuses the effective-dated Assignment Engine pattern | 🟢 Accepted | Academic, HR |
| [BUS-0020](adr/business/0020-academic-learning-boundary.md) | Subject (Academic) vs. Course Template (Learning) boundary formalized; deeper content split remains Proposed | 🟢 Accepted (boundary) / 🟡 Proposed (deeper split) | Academic, Learning |
| [BUS-0021](adr/business/0021-adr-granularity-one-decision-per-adr.md) | ADRs hold one central decision; themed ADRs split once sub-decisions stop being coupled (forward-looking, BUS-0017 not retroactively split) | 🟢 Accepted | All (documentation architecture) |
| [BUS-0022](adr/business/0022-reception-domain-boundary.md) | Reception owns Visitor/Visit identity; Smart Campus owns only Visitor Access Credential/Access Events and consumes Reception's Visitor — a naming collision, not an ownership transfer | 🟢 Accepted | Reception, Smart Campus |
| [BUS-0023](adr/business/0023-reception-external-party-and-visit-lifecycle.md) | External Party (optional Master Data), polymorphic Correspondence routing, full Visit lifecycle documented end-to-end | 🟢 Accepted (External Party itself 🟡 Proposed) | Reception |
| [BUS-0024](adr/business/0024-hr-organizational-structure.md) | HR Organizational Structure — Department self-referential/nullable-Branch, Position "reports to" default-with-override | 🟢 Accepted | HR, Academic |
| [BUS-0025](adr/business/0025-hr-assignment-model.md) | HR Assignment Model — Employment and Position Assignment are two distinct, related `ADR-0024` instances, validates BUS-0019's own claim | 🟢 Accepted | HR, Academic, Learning |
| [BUS-0026](adr/business/0026-employee-as-person.md) | Employee is a Person-specialization, per `ADR-0001` | 🟢 Accepted | HR |
| [BUS-0027](adr/business/0027-contract-model.md) | Contract is a distinct, versioned document attached to Employment, not Employment itself | 🟢 Accepted | HR |
| [BUS-0028](adr/business/0028-employment-lifecycle.md) | Employment lifecycle — Probation → Active → (On Leave) → Terminated/Resigned/Retired | 🟢 Accepted | HR |
| [BUS-0029](adr/business/0029-position-lifecycle.md) | Position lifecycle — Open/Frozen/Eliminated stored, Filled always derived from Assignment | 🟢 Accepted | HR |
| [BUS-0030](adr/business/0030-guardian-relationship-ownership.md) | Guardian/`guardian_student` owned by a future Parents/Family domain, not Students; `ADR-0024` foreclosed by its own Out of Scope | 🟢 Accepted | Students, Parents/Family (future) |
| [BUS-0031](adr/business/0031-learning-eligibility-boundary.md) | Learning Participation Actor stays Person; Enrollment becomes a required gate when the Actor is a Student, reusing BUS-0025's Employment-gate shape | 🟢 Accepted | Students, Learning, Academic |
| [BUS-0032](adr/business/0032-academic-term-introduced-as-master-data.md) | `Term` introduced as a real Master Data entity (child of Academic Year, `AcademicYear`'s own 3-state lifecycle reused) after a confirmed near-term product requirement for 2–3 terms/year reversed this ADR's own same-day first draft ("Term = AcademicYear"); `SubjectOffering` stores both `term_id` and `academic_year_id` directly (not derived-only), kept consistent by a formal Invariant (`TermAcademicYearMismatchException`); `Section` stays year-scoped, unchanged; Academic Calendar's separation stays unresolved but confirmed non-blocking | 🟢 Accepted | Academic, Learning, Students |
| [BUS-0033](adr/business/0033-facilities-domain-room-ownership.md) | `Room` (BUS-0018's own undefined Subject Offering axis) resolved by introducing a new `Facilities` domain (reusing the Blueprint's own already-named placeholder), scoped strictly to Physical Space Master Data — a binding Non-Goals section excludes Timetables/Reservations/Physical Security/Environmental Controls/**Assets**/Inventory by name, so `Assets` (equipment/machinery, a fundamentally different acquire/depreciate/maintain lifecycle) stays its own separate future domain, per the Dependency Map's own existing "cross-reference not merge" guidance. Room may not live inside Academic (binding user instruction) and does not fit Inventory (not an OT domain, issue/return/consume lifecycle) or Smart Campus (safety-critical command control, not reference-data ownership). Consumption is hard-restricted to a future `RoomCatalogService`-style contract — `Academic -> Facilities\Models\Room` is explicitly disallowed. Facilities' actual implementation is Designed, Not Yet Scheduled — this ADR resolves ownership and boundary only | 🟢 Accepted | Academic, Facilities (new), Inventory, Smart Campus |

### Open Architecture Questions

Permanent section. Nothing here is ever silently deleted — an item leaves only by being resolved and moved into its own ADR above.

1. **What is the final short name/acronym for the Learning domain?** "Learning Intelligence Platform" is accepted conceptually (BUS-0007); "LIP" was flagged as an awkward initialism and left unresolved. `learning.md`'s heading still reads "LMS (Distance Learning)" pending this.
2. **Health Clinic's, School Operations', and Smart Campus's existing AI Opportunities sections need a retroactive correction pass** to reference the unified `AIDecision` primitive (BUS-0003) instead of each domain's own ad-hoc "human must confirm" wording. Not yet done.
3. **Event Stream (BUS-0005) is named but not formally specified** as a Core Platform Service — no design pass has been done on it yet.
4. **The Privacy/Consent domain (BUS-0006) does not exist** anywhere in this document. It's now a blocking dependency for Learning's AI features, not just a noted gap.
5. **Administration, Platform Services, Admissions, and Accounting remain on the retired v1 template** and have not been migrated to the canonical template. Academic, Learning, HR, and Students are all certified Reference Blueprints — see Migration Guidelines. Scope of the remaining backfill for the untouched four is undecided.
6. **Emergency Coordination's ownership correction** (documented inline in `school-operations.md`) has never been captured as a formal ADR. Not yet fixed.
7. **Three named, unresolved sub-issues inside the accepted Course Offering structure (BUS-0011):** single-valued Enrollment per Offering can't express a mixed audience; Teacher is single-cardinality with no co-teaching/TA path; Meeting Provider is Offering-level, not session-level.
8. **Four Learning entities are Proposed but not decided** (BUS-0012–0015). Do not build against these without first converting the relevant ADR to Accepted.
9. **Resolved:** `learning.md`'s own body text was rewritten to fold in BUS-0001–0015 and BUS-0020 as part of its Phase 2 design work. What remains open is not the prose but the canonical-template *section* migration (Mobile Features, Reports, Commercial Differentiators, and others) — tracked in Migration Guidelines, not here.
10. **Academic Calendar's separation from Academic Year** (raised in the general Academic review) remains 🟡 Proposed — not decided. [BUS-0032](adr/business/0032-academic-term-introduced-as-master-data.md) (2026-07-28) discusses this alongside the adjacent "Term" question and concludes it does not block Subject Offering's own design — but does not resolve it; still tracked here.
11. **The deeper Subject Sequencing (Academic) vs. Curriculum Content (Learning) split** (BUS-0020) remains 🟡 Proposed — only the Subject/Course Template naming boundary itself is settled. Should be resolved when Learning's own v1→v3 retrofit happens.
12. **Whether Reception should subscribe to the cross-cutting Emergency Coordination service** (visitor accountability during an active emergency) was raised while designing Reception but not decided — 🟡 Proposed, not built against.
13. **`learning.md`'s pre-existing Course Offering registration mechanism ("academically-tied vs. independent Offering") has never been reconciled with `BUS-0031`'s Enrollment-gate rule** (Actor-type-based, not Offering-type-based). Found during Students Freeze Preparation (2026-07-27); confirmed non-blocking for Students' own certification, since `students.md` states `BUS-0031` completely and correctly on its own side. Tracked here as a Learning Blueprint maintenance item — not resolved, not built against either framing until reconciled.

### Architecture Assumptions

Recorded explicitly per the golden rule — an assumption stands until it's either confirmed (folded into the relevant ADR) or disproven (replaced by a new ADR documenting the correction).

- **Assumption:** "Teacher" in Learning's Course Offering is single-cardinality (one instructor per Offering). Not yet challenged or confirmed.
- **Assumption:** Corporate Training (named as a future requirement) will reuse the same dedicated-instance-per-customer model as School deployments, rather than requiring a distinct deployment profile. Connects back to the earlier Organization/School multi-vertical discussion; not decided.

---

## Migration report (2026-07-22 refactor)

Every section from the previous single-file `BUSINESS_BLUEPRINT.md` was moved, not rewritten — no business decision changed during this refactor.

| Original section | Moved to | Notes |
|---|---|---|
| "What this document is, and isn't" | This file, Overview | Unchanged |
| Vocabulary reuse list | This file, Overview | Unchanged |
| Classification algorithm | This file, Documentation standards | Unchanged |
| Template versions | This file, Documentation standards | Unchanged |
| Phasing note | This file, Domain Map (remaining-domains list) | Unchanged, reformatted as a table |
| Scope note (OT) | Referenced from `school-operations.md` and `smart-campus.md`; standing rule already lives in `CLAUDE.md` | Not duplicated — the rule has one home |
| Correction (Emergency Coordination) | `school-operations.md` | Moved in full, cross-referenced from `health-clinic.md`, `smart-campus.md`, `hr.md`, `students.md` |
| "Courses/Homework/... are submodules of LMS" correction | `learning.md` | Moved in full |
| Governance (Status legend, Decision Log, Open Questions, Assumptions) | This file | Kept here per requirement — this is exactly what a master index should hold |
| Domain 1 (Administration), full body | `business-domains/administration.md` | Verbatim |
| Domain 2 (Platform Services), full body | `business-domains/platform-services.md` | Verbatim |
| Domain 3 (Academic), full body | `business-domains/academic.md` | Verbatim |
| Domain 4 (Students), full body | `business-domains/students.md` | Verbatim |
| Domain 5 (Admissions), full body | `business-domains/admissions.md` | Verbatim |
| Domain 6 (HR), full body | `business-domains/hr.md` | Verbatim |
| Domain 7 (Accounting), full body | `business-domains/accounting.md` | Verbatim |
| Domain 8 (LMS), full body + doc-status block | `business-domains/learning.md` | Verbatim, all 12 ADR cross-references preserved |
| Domain 9 (Health Clinic), full body | `business-domains/health-clinic.md` | Verbatim |
| Domain 10 (School Operations), full body + Correction note | `business-domains/school-operations.md` | Verbatim; Correction note moved here in full (previously in the index) |
| Domain 11 (Smart Campus), full body | `business-domains/smart-campus.md` | Verbatim |
| Domain 12 (Inventory), full body + doc-status block | `business-domains/inventory.md` | Verbatim, all 3 ADR cross-references preserved |
| "Next phase" footer | This file, Domain Map (remaining-domains list + cross-cutting corrections note) | Content preserved, reformatted |

**Confirmation: no content was lost.** Every domain's full 20+-section specification (Purpose through Commercial Differentiators/Future Growth) is reproduced in full in its own file, not summarized. Every ADR cross-reference, every status tag, every "not yet done" flag, and every cross-domain reference carried over exactly. The only new content added during this refactor is navigational: a back-link to this index, a Related ADRs/Related Domains header, and a Navigation footer, on each of the 12 domain documents — none of it changes what any domain document says.

**Folder name.** `docs/business-domains/` — confirmed, with one addition to the justification already given: this mirrors the naming already adopted for `docs/adr/business/` (as opposed to `docs/adr/*`), so the "business" qualifier consistently distinguishes both parallel tracks (ADRs and domain documents) from the frozen technical documents (`docs/DOMAIN_BLUEPRINT.md`, `docs/adr/*`) they sit next to. `docs/domains/` was the other option on the table and is rejected specifically because it would sit in the same directory as `docs/DOMAIN_BLUEPRINT.md` — the exact kind of near-collision this project has corrected before (e.g., "Core Services" vs. "Core" in an earlier architecture discussion).
