# BUS-0018: Academic Subject Model — Subject, Subject Offering, Subject Version, Equivalency, Electives

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Academic, Learning (boundary formalized in BUS-0020), Students (Enrollment/Grade reference Subject Offering), HR (Teacher assignment to Subject Offering, via BUS-0019)

**Related ADRs:** BUS-0001 (Course Template versioning, reused directly for Subject Version), BUS-0017 (Curriculum Specification's Requirements reference Subject), BUS-0019, BUS-0020

## Context

Raised via a dedicated Subject Model review, requested before documenting Academic. "Math" vs. "Grade 10 Mathematics" vs. "Calculus I" vs. "Mathematics 101" surfaced that "Subject" might be several distinct concepts rather than one — and that Academic's natural word for its catalog entity, "Course," collides with Learning's already-accepted Course Template (BUS-0001).

## Problem

Is Subject Catalog / Course / Course Instance / Subject Offering / Subject Version / Credit Hours / Prerequisites / Electives / Mandatory Subjects / Cross-listed Subjects / Subject Equivalency / Subject Replacement / Subject Retirement one entity, several, or does part of this actually belong to Learning?

## Alternatives Considered

- **Naming Academic's catalog entity "Course"** — rejected. Learning already owns that word for its content/pedagogy container (Course Template, BUS-0001); reusing it for Academic's catalog/credit entity would be the same class of collision as Program/Program-of-Study.
- **Modeling K-12 "Subject" and university "Course" as separate entities** — rejected. Both play the identical structural role (a credit/catalog-level teachable unit with prerequisites, later scheduled into a term); this is the same regional-vocabulary-vs-one-concept pattern already resolved for Curriculum Path.
- **Modeling Cross-listed Subjects, Subject Equivalency, and Subject Replacement as three separate entities** — rejected. All three are the same Subject-to-Subject equivalence mapping, differing only in a type/reason attribute (concurrent cross-listing, transfer equivalency, curriculum-reform replacement).
- **Treating Mandatory/Elective as a Subject-level flag** — rejected. The same Subject (e.g., Art) can be mandatory in one Curriculum Path and elective in another; the flag belongs on the (Subject, Curriculum Specification Requirement) membership link, not on Subject itself.
- **Inventing a new versioning mechanism for Subject Version** — rejected. BUS-0001's Course Template versioning already solves the identical problem (a catalog-level definition changing in a way that mustn't retroactively bind already-enrolled students); reuse it directly.

## Final Decision

- **Subject** (Academic Master Data, canonical schema/API name; "Course" is permitted only as a display label in higher-ed-flavored deployment UIs, never the underlying name) — the catalog-level entity: discipline/department reference, nullable Credit Hours (K-12 deployments frequently don't use them; universities and vocational programs do), prerequisite relationships, active/retired lifecycle status.
- **Subject Version** — reuses BUS-0001's Course Template versioning discipline directly, applied to Subject instead of Learning's content container.
- **Subject Offering** (collapsing "Course Instance" — same entity, two vocabularies) — Subject × Term × Section × Teacher × Room × Schedule: a period-scoped operational record. This, not the abstract catalog Subject, is what Grade/Enrollment reference and what the deferred Timetabling engine schedules against.
- **Cross-listed Subjects / Subject Equivalency / Subject Replacement** — one mechanism: a typed Subject-to-Subject equivalence relationship (type: cross-listing, transfer equivalency, curriculum replacement).
- **Electives / Mandatory Subjects** — an attribute of the (Subject, Curriculum Specification Requirement) membership link, not of Subject.
- **Subject Retirement** — a lifecycle status plus effective date on Subject, non-destructive, matching the project's existing no-hard-delete-on-referenced-Master-Data discipline.

## Why This Decision Was Chosen

This is the fourth time in this same review sequence that two domains reaching for the same everyday word ("Program," "Department," now "Course") turned out to name genuinely different things — treating it as a recurring pattern rather than a one-off collision is what allowed a clean resolution instead of a fifth ad-hoc fix appearing later. Collapsing Cross-listing/Equivalency/Replacement into one mechanism, and reusing BUS-0001's versioning rather than inventing a second one, both apply the same "don't build a second mechanism for an already-solved problem" discipline used throughout this domain's design.

## Consequences

Easier: Academic's Subject and Learning's Course Template can be built independently with no naming or ownership ambiguity in schema, code, or API surfaces; one equivalence-relationship table replaces what would otherwise have been three near-identical ones. Harder: any existing prose or future UI copy that informally says "Course" for the academic/credit sense must be understood as Subject internally — "Course" is a label, never the entity name.

## Future Implications

Subject Offering is exactly the entity the deferred Timetabling scheduling engine (🔵, general Academic review) will schedule against when that work starts — no redesign needed at that point.

## Traceability

- **Business requirement:** support K-12, Universities, Colleges, and vocational education without a redesign, and without corrupting the platform's existing Learning vocabulary.
- **Introduced in:** the dedicated Subject Model review, requested before documenting Academic.
- **Depended on by:** BUS-0017 (Requirements reference Subject), BUS-0019 (teacher assignment to Subject Offering), BUS-0020 (the Academic/Learning boundary itself), the deferred Timetabling engine.
