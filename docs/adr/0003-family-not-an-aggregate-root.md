# ADR-0003: Family Is Not an Aggregate Root

**Status:** Accepted

**Date:** 2026-07-01

## Context

The system needs to represent guardian-student relationships, sibling/kinship relationships, and household billing groupings, in a bilingual (Arabic/English) context where kinship terminology (e.g. paternal vs. maternal uncle/grandfather) is more granular than English.

## Decision

No `Family` aggregate exists. Instead: (1) `guardian_student`, a safety-critical join between the existing Guardian and Student aggregates, carrying custody/pickup-authorization state and effective dates; (2) `person_relationships`, a generic informational graph at the Person level (sibling, grandfather, uncle, etc.), with `relationship_type` as a translatable lookup table, not an enum; (3) `households`/`billing_groups`, a thin, explicitly administrator-curated grouping consumed by Finance, deliberately decoupled from the relationship graph. A "family tree" view in the UI is a derived read over (1) and (2), never a stored row.

## Consequences

Divorced-parent and blended-family scenarios (a child spanning two households) are represented naturally, since no single row is forced to represent a non-partitioned social structure. Billing groupings can diverge from biological/legal relationships without corrupting either concept. Cost: there is no single "Family" table to query — any feature needing a household view must traverse two tables, and `relationship_type` needed a translatable lookup table (not an enum) specifically to represent Arabic kinship terms English collapses into one word.

## Alternatives Considered

- **A single `families` table with membership.** Rejected — a shared child of divorced parents in two households would need to belong to two Family rows simultaneously, violating "one consistency boundary per aggregate," and the table would inevitably accumulate billing/address/custody concerns that don't share a lifecycle (the classic God Object failure mode).

## References

`docs/DOMAIN_BLUEPRINT.md` §11.
