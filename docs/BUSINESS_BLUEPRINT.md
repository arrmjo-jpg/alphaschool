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

## Documentation standards

### Classification algorithm (used throughout every domain document)

1. Does it produce an immediate side effect rather than change a rule for later? → **Action**, not configuration.
2. Does it have identity, referenced by other records via FK? → **Master Data**, not configuration.
3. Scoped to one user, no approval ceremony? → **User Preference**.
4. Governs security/access/operations, independent of which modules exist? → **Administration**.
5. Exactly one deployment-wide value, no single domain owns its meaning? → **Platform Configuration**.
6. Otherwise → **Domain Configuration**, owned by the one domain it belongs to.

### Domain document template versions

**v1**: Purpose, Responsibilities, Submodules, Master Data, Configuration, Business Workflows, Permissions, Reports, Mobile Applications, Integrations, Cross-Domain Dependencies, Future Growth.

**v2**: v1's dimensions renamed/expanded — Purpose, Responsibilities, Business Capabilities, Submodules, Master Data, Settings, Workflows, Domain Events, Automation Opportunities, AI Opportunities, Provider Slots, Public APIs, Extension Points, Mobile Features, Dashboards, Reports, KPIs, Security Classification, Permissions, Audit Requirements, Data Ownership, Future Expansion.

**v3** (binding from Domain 11 onward): v2 plus a closing **Commercial Differentiators** section — see `CLAUDE.md`'s standing rule of the same name.

Domains 1–8 remain on v1 and are **retrofit-pending** to v3 — a known, tracked, not-yet-scheduled task (see Open Architecture Questions).

### File structure per domain (BUS-0016)

Every domain is a single file under `docs/business-domains/` until it actually needs otherwise. **A domain is promoted to its own folder only when its file exceeds roughly 250–300 lines** — not preemptively, not uniformly, per BUS-0016. When that happens, sections map onto the existing v3 template rather than a new taxonomy: `README.md` (Purpose/Responsibilities/Business Capabilities/Commercial Differentiators), `entities.md` (Submodules/Master Data/Settings), `workflows.md` (Workflows/Domain Events/Automation Opportunities), `ai.md` (AI Opportunities — only for domains with real AI design weight), `integrations.md` (Provider Slots/Public APIs/Extension Points/Mobile Features), `reports.md` (Dashboards/Reports/KPIs), `permissions.md` (Security Classification/Permissions/Audit Requirements/Data Ownership), `decisions.md` (Related ADRs + open items), `diagrams/`. Learning is the domain most likely to cross this threshold first, expected at its v1→v3 retrofit, not before.

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

| # | Domain | Template | Document |
|---|---|---|---|
| 1 | Administration | v1 | [administration.md](business-domains/administration.md) |
| 2 | Platform Services | v1 | [platform-services.md](business-domains/platform-services.md) |
| 3 | Academic | v3 | [academic.md](business-domains/academic.md) |
| 4 | Students | v1 | [students.md](business-domains/students.md) |
| 5 | Admissions | v1 | [admissions.md](business-domains/admissions.md) |
| 6 | HR | v1 | [hr.md](business-domains/hr.md) |
| 7 | Accounting | v1 | [accounting.md](business-domains/accounting.md) |
| 8 | LMS (Distance Learning) — rename to Learning Intelligence Platform pending (BUS-0007) | v1 | [learning.md](business-domains/learning.md) |
| 9 | School Health Clinic | v3 | [health-clinic.md](business-domains/health-clinic.md) |
| 10 | School Operations & Campus Automation | v3 | [school-operations.md](business-domains/school-operations.md) |
| 11 | Smart Campus & Physical Security | v3 | [smart-campus.md](business-domains/smart-campus.md) |
| 12 | Inventory | v3 | [inventory.md](business-domains/inventory.md) |
| 13 | Reception | v3 | [reception.md](business-domains/reception.md) |

**Remaining, not yet documented**: Transportation, Library, Procurement, Assets, Facilities, Communications, Parents, Alumni, Activities, Events, Clubs, Summer Camp, Scout Camp, Compliance, Reports, Analytics, Examinations, Discipline, Special Education, Fundraising, Scholarships. Reception was added out of this list, by explicit request, ahead of the retrofit-priority queue below — it's a new domain, not a v1→v3 retrofit, so it doesn't reorder that queue.

**Retrofit priority (agreed 2026-07-22).** With Inventory and Academic now at v3, the platform's documentation maturity is uneven across domains, and Academic in particular shares direct boundaries (Assignment/Department/Position via BUS-0017/BUS-0019, Curriculum Path/Specification via BUS-0017) with several still-v1 domains. Rather than opening further new-domain discovery, the agreed next order is: **HR** (shares Assignment/Department/Position with Academic) → **Students** (consumes Enrollment/Curriculum Path/Curriculum Specification) → **Admissions** (feeds Students) → **Learning** (largest pending retrofit, already carries 12 ADRs — BUS-0001–0007, BUS-0011–0015, BUS-0020 — none yet folded into its own prose). Administration, Platform Services, and Accounting remain v1 and follow after these four. This ordering, not just the decision to retrofit, is the thing being recorded here — so it isn't re-derived or silently reordered in a future session.

**Cross-cutting corrections not yet owned by any single domain**: Emergency Coordination (a Core Platform Service, promoted out of School Operations — see [school-operations.md](business-domains/school-operations.md)'s Correction note; no formal ADR yet, tracked below); Event Stream (named in BUS-0005, not yet formally specified as its own Core Platform Service); the Privacy/Consent domain BUS-0006 depends on (doesn't exist yet).

## Dependency Map

| Domain | Consumes from | Feeds into |
|---|---|---|
| Administration | — (depends on nothing) | Every domain (permissions, Provider Registry credentials) |
| Platform Services | — | Accounting, Academic, HR (Document Templates) |
| Academic | HR (Employee/Position for Teacher/Coordinator Assignment; Department for Academic Department cross-reference) | Students, Accounting, Learning, School Operations, Inventory |
| Students | Academic | Accounting, Transportation, Library, Learning, Health Clinic, Smart Campus |
| Admissions | — | Students (handoff on acceptance) |
| HR | — | Payroll (future), Academic (teacher assignments) |
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

### Open Architecture Questions

Permanent section. Nothing here is ever silently deleted — an item leaves only by being resolved and moved into its own ADR above.

1. **What is the final short name/acronym for the Learning domain?** "Learning Intelligence Platform" is accepted conceptually (BUS-0007); "LIP" was flagged as an awkward initialism and left unresolved. `learning.md`'s heading still reads "LMS (Distance Learning)" pending this.
2. **Health Clinic's, School Operations', and Smart Campus's existing AI Opportunities sections need a retroactive correction pass** to reference the unified `AIDecision` primitive (BUS-0003) instead of each domain's own ad-hoc "human must confirm" wording. Not yet done.
3. **Event Stream (BUS-0005) is named but not formally specified** as a Core Platform Service — no design pass has been done on it yet.
4. **The Privacy/Consent domain (BUS-0006) does not exist** anywhere in this document. It's now a blocking dependency for Learning's AI features, not just a noted gap.
5. **Administration, Platform Services, Students, Admissions, HR, Accounting, and Learning remain on the v1 template** and have not been retrofitted to v3 (Academic completed this retrofit via BUS-0017–0020). Scope of the remaining backfill is undecided.
6. **Emergency Coordination's ownership correction** (documented inline in `school-operations.md`) has never been captured as a formal ADR. Not yet fixed.
7. **Three named, unresolved sub-issues inside the accepted Course Offering structure (BUS-0011):** single-valued Enrollment per Offering can't express a mixed audience; Teacher is single-cardinality with no co-teaching/TA path; Meeting Provider is Offering-level, not session-level.
8. **Four Learning entities are Proposed but not decided** (BUS-0012–0015). Do not build against these without first converting the relevant ADR to Accepted.
9. **`learning.md`'s own body text has not been rewritten** to reflect BUS-0001–0015 — the ADRs exist and are cross-referenced, but the prose itself is still the original v1 write-up.
10. **Academic Calendar's separation from Academic Year** (raised in the general Academic review) remains 🟡 Proposed — not decided.
11. **The deeper Subject Sequencing (Academic) vs. Curriculum Content (Learning) split** (BUS-0020) remains 🟡 Proposed — only the Subject/Course Template naming boundary itself is settled. Should be resolved when Learning's own v1→v3 retrofit happens.
12. **Whether Reception should subscribe to the cross-cutting Emergency Coordination service** (visitor accountability during an active emergency) was raised while designing Reception but not decided — 🟡 Proposed, not built against.

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
