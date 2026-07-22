# BUS-0001: Course Template Requires Explicit Versioning; Offerings Pin to a Specific Version

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Learning (Course Template, Course Offering), Platform Services (Versioning Pattern)

**Related ADRs:** none yet — first Business-track ADR

## Context

The proposed Course Template / Course Offering model (Template = content only; Offering = a scheduled run of a Template) was reviewed as an ERP/LMS architect exercise. Template had no versioning concept — it was treated as a single mutable object that every Offering runs against live.

## Problem

What happens when a Template's content is edited while Offerings are actively running against it?

## Alternatives Considered

- **No versioning, edits apply live to every running Offering** — the as-proposed design. Rejected: a mid-term content or Question Bank edit would retroactively change what students already sat an exam on, silently corrupting grading history and invalidating already-completed assessments.
- **Snapshot the whole Template at Offering-creation time (deep copy)** — rejected as wasteful and unable to represent "apply this correction to the next cohort but not this one" without full duplication of unrelated, unchanged content.
- **Explicit Template versions, Offering pins to one** — accepted.

## Final Decision

Course Template gets explicit version identity. Every Course Offering pins to a specific Template version at creation time, not to a live pointer to the Template's current state. Editing a Template creates a new version; existing Offerings are unaffected until deliberately migrated to a newer version.

## Why This Decision Was Chosen

This is the same Versioning Pattern already established and frozen elsewhere in this project's architecture — "never overwrite a fact with real historical weight; append a new row instead" — already applied to Enrollment, Employment, and identity documents. Course content consumed by a graded cohort has exactly that kind of historical weight. Extending an existing, proven pattern rather than inventing a new one for Learning specifically.

## Consequences

Easier: content corrections can be made without risk to running cohorts; exam integrity is preserved; a Template's history becomes auditable. Harder: Offering creation now requires a version selection step, and "which version is a given Offering on" becomes a real piece of state to surface in the UI.

## Future Implications

This is also the mechanism that will host Course Template's eventual reusability across a content marketplace (already flagged as a Commercial Differentiator, currently unbuilt) — a licensed/imported Template package is naturally a specific version, not a live-editable original.

## Traceability

- **Business requirement:** the platform must support running Offerings (School, Public, Paid) concurrently against shared content without cross-contaminating grading integrity.
- **Introduced in:** the "AlphaSchool ERP – Deep Architecture Review (Learning/Course design)" critique turn.
- **Depended on by:** BUS-0004 (Concept Graph phased adoption) — Template versions are also where Concept Graph tagging will eventually live per-version.
