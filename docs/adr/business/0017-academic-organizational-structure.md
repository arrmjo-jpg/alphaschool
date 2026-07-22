# BUS-0017: Academic Organizational Structure — Faculty, Academic Department, Stage, Curriculum Path, Curriculum Specification

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Academic, HR (Academic Department cross-references HR's Department; Faculty may reference a Dean Position), Students (Curriculum Path/Curriculum Specification binding lives on Enrollment)

**Related ADRs:** BUS-0018 (Curriculum Specification's Requirements reference Subject), BUS-0019 (Coordinator/Department-Head effective-dated scope), BUS-0009 (same Setting-with-override mechanism reused for catalog-year lock behavior)

## Context

Raised via a dedicated review of the Academic Organizational Structure, requested before accepting the general Academic architectural review's findings. Faculty, Academic Department, Stage, Educational Track, Stream, Program of Study, Major, Specialization, and Curriculum Path needed ownership and lifecycle determined, explicitly required to generalize beyond K-12 to Universities, Colleges, Institutes, multi-program schools, international curricula, and vocational education — not assumed K-12-only.

## Problem

Are Faculty/Academic Department/Stage/Track/Stream/Major/Specialization/"Program of Study" distinct entities, structures already owned by other domains (HR, Organization), or a new Academic-owned cluster? Does the official requirement structure behind a curriculum path deserve its own aggregate, separate from the path's own identity?

## Alternatives Considered

- **Naming the identity-hierarchy entity "Program of Study" or "Academic Program"** — rejected. The platform already has a frozen, load-bearing concept named Program (School, Kindergarten — its own enrollment/branding/portal). Reusing the word for a university degree path would silently collide with that meaning across documentation, code, and API surfaces.
- **Modeling Track / Stream / Major / Specialization as separate entities**, one per regional or institutional vocabulary — rejected. All five name the same underlying concept (a named sequence of Subjects a student follows within one enrollment); building five entities for one concept would repeat a mistake this project has already corrected once (treating a naming variation as a structural difference).
- **Merging HR's Department and Academic's curriculum-owning department into one aggregate** — rejected. They track different concerns (staffing/reporting-line vs. curriculum/subject ownership) with independent lifecycles; the same department in the real world, two aggregates, cross-referenced by a shared identifier.
- **Loading Requirements and Completion Rules directly onto Curriculum Path** — rejected. Curriculum Path's identity ("BS Computer Science") is stable for years; its requirements change at every curriculum reform or catalog year. Mixing the two recreates the exact problem BUS-0001's Course Template versioning already exists to solve, just for a different entity.
- **Naming the requirement-holding entity "Study Plan"** — rejected as ambiguous. In common registrar usage, "Study Plan" means a student's own personal plan, not the official curriculum requirement definition, and risks colliding with future Student Planner / Degree Audit / Graduation Planner features.

## Final Decision

- **Faculty** — new, optional/nullable Academic Master Data (University-only tier; a K-12 deployment never populates it). The word "Faculty" is reserved exclusively for this administrative-division sense; it must never be used elsewhere in the platform's vocabulary as a synonym for teaching staff (that's simply HR's Employee data).
- **Academic Department** — new Academic Master Data, cross-referenced to HR's Department by a shared identifier, never merged. HR owns staffing/reporting-line/budget; Academic Department owns which Subjects and Curriculum Paths it's academically responsible for, and optionally sits under a Faculty.
- **Stage** — new Academic Master Data, groups Grade Levels (or, in a University context, the equivalent Undergraduate/Graduate/Doctoral tier). One concept; the displayed label is chosen per institution type, not a different entity per label.
- **Curriculum Path** — new, self-referential Academic Master Data (`parent_curriculum_path_id`, the same self-reference shape already proven for Branch and Program), unifying Track/Stream/Major/Specialization/"Program of Study" (degree sense) under one canonical entity. The displayed label (Track, Stream, Major, Specialization, Program of Study) is a Platform Configuration/localization choice per institution or region — never a separate entity, and never named "Program" or "Academic Program" in schema or API.
- **Curriculum Specification** (final name, chosen over "Study Plan") — new, versioned Academic Master Data: the official, registrar-owned requirement and completion-policy definition for one Curriculum Path, versioned the same way Subject is (BUS-0018) and Course Template already is (BUS-0001). Owns two sibling structures:
  - **Requirements** — categories with a satisfaction rule each: a specific Subject, pick-N-of-M from a pool, or a minimum credit-hour threshold from a category.
  - **Completion Rules** — minimum GPA, minimum credits, mandatory internship/thesis/capstone, language requirement, community-service hours — genuine policy gates, not expressible as Subject requirements, that don't belong on Subject, Curriculum Path, or Enrollment.
- **Coordinator / Department-Head HR-Position-vs-Academic-scope split** — 🟡 Proposed in the general Academic review, upgraded here to 🟢 Accepted: Academic Department being its own aggregate is precisely the mechanism that resolves it. HR owns the Position; Academic Department carries the academic-authority scope (see BUS-0019 for the effective-dating mechanism).
- **Generalization check**: a K-12 Curriculum Specification is simply the lightweight case of the same mechanism — a fixed Subject list per grade, no elective pools or credit thresholds — not a University-only add-on forced onto K-12.

## Why This Decision Was Chosen

Every resolution here reuses a pattern already proven elsewhere in this platform rather than inventing a new one: the self-referential tree (Branch, Program), the identity-vs-versioned-content split (Subject/Subject Version, Course Template/Course Template Version), and the cross-referenced-not-merged dual-aggregate shape. Naming decisions were tested explicitly against the platform's existing frozen vocabulary (Program) rather than assumed safe by default — the same discipline that caught the Program-of-Study collision in the first place.

## Consequences

Easier: a University, vocational institute, or international-curriculum deployment can populate Faculty/Academic Department/Curriculum Path/Curriculum Specification meaningfully with zero schema change; a plain K-12 deployment simply leaves Faculty null and uses one flat Curriculum Specification per Curriculum Path. Harder: Curriculum Specification is a genuinely new aggregate with its own versioning discipline to build and maintain — teams must resist writing requirements directly onto Curriculum Path as a shortcut.

## Future Implications

Curriculum Specification's Completion Rules will need to consume (never own) Students' GPA calculation when evaluating minimum-GPA gates — a consume-not-own dependency, not yet formally specified. Whether a student's bound Curriculum Specification version stays locked at declaration or floats to the latest published version is deferred to a Setting-with-Program-override, reusing BUS-0009's mechanism rather than inventing a new policy switch — not yet built.

## Traceability

- **Business requirement:** support K-12, Universities, Colleges, Institutes, multi-program schools, international curricula, and vocational education without architectural change.
- **Introduced in:** the dedicated Academic Organizational Structure review, requested before accepting the general Academic review's findings.
- **Depended on by:** BUS-0018 (Subject, referenced by Requirements), BUS-0019 (Coordinator/Department-Head scope), Enrollment (binds a student to one Curriculum Path + one Curriculum Specification version, effective-dated).
