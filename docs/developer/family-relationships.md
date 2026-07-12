# Family Relationships (Sprint 2.5)

**Status:** Complete, frozen as `v0.8-people-family`. Governed by `docs/DOMAIN_BLUEPRINT.md` §11 and `docs/adr/0003-family-not-an-aggregate-root.md`. No further Family-module work unless a real implementation bug, a security issue, or a new approved ADR requires it.

## No Family aggregate — two tiers instead

A divorced-parents scenario (one child, two households) breaks a single `Family` table outright — the child would need to belong to two rows simultaneously, or one row would falsely represent two non-cohabiting households as one unit. Instead, two genuinely separate tables, at different stakes:

1. **`guardian_student`** — the safety-critical join between the existing Guardian and Student aggregates. Custody, pickup authorization, verification, effective-dated via `HasTemporalAssignment`.
2. **`person_relationships`** — a generic, informational Person-to-Person graph (siblings, spouses, former spouses, paternal/maternal aunts/uncles/grandparents, or any future kind). No custody weight, no effective-dating.

**Never merge these under schedule pressure** — this was the Playbook's own named risk for this sprint, and the two really do serve different stakes: one needs verification and legal-weight history, the other doesn't.

## `relationship_types` — one shared, scoped vocabulary

Both tables above reference the same `relationship_types` lookup table, distinguished by a `scope` column (`guardian_student` vs. `person_relationship`) — the same pattern already used for Tags (Addendum D2), rather than two near-duplicate translation tables. `code` is immutable once set and is the only value business code may reference; display names are Spatie-Translatable. This exists specifically because Arabic distinguishes paternal from maternal kinship terms (عم vs خال, جد لأب vs جد لأم) that English collapses into one word — a PHP enum can't represent that distinction, only a real lookup table can.

**Reference-data deletion policy, three layers, applied consistently across every reference table this sprint introduced** (`RelationshipType`, and proactively on `Household`/`BillingGroup` too, rather than waiting to retrofit them like `Branch`/`Role`/`ReasonCode` had to be):
1. No delete workflow in the application layer — only activate/deactivate.
2. Each model's own `deleting()` guard throws a `RuntimeException` outright.
3. Every table that references one of these (`guardian_student.relationship_type_id`, `person_relationships.relationship_type_id`, `household_members.household_id`, `billing_group_members.billing_group_id`, etc.) uses `restrictOnDelete()` as the database-level backstop.

**Scope validation is symmetric.** `GuardianStudent` rejects a `relationship_type_id` from the `person_relationship` scope; `PersonRelationship` rejects one from the `guardian_student` scope. This was originally asymmetric (only `PersonRelationship` validated its scope) — found as a consistency bug during Step 3's review and fixed to hold in both directions.

## `guardian_student` — `HasTemporalAssignment`'s first real consumer

Two genuine findings surfaced only because this was the trait's first real production use (previously exercised only by its own architecture-level tests):

- **A real, reproducible bug, not a theoretical one:** Eloquent's `date` cast truncates time-of-day only on *display*, not in the raw stored value. A row created with `effective_from = now()` (rather than `now()->startOfDay()`) stored the full timestamp, causing `scopeAsOf()`'s `Carbon::parse($date)->startOfDay()` comparison to wrongly exclude same-day rows created after midnight. Fixed locally in `GuardianStudent` via explicit mutators — a deliberate, temporary stopgap, not the final fix.
- **No concurrency safety:** `guardAgainstOverlap()` is a fetch-then-check-then-write with no row lock, unlike `NumberGeneratorService`'s proven `lockForUpdate()` handling.

Both were confirmed to be properties of `HasTemporalAssignment` itself (Core, Sprint 1.1) — every future consumer (Enrollment, Employment, teacher/committee/route assignments, Fee Plan versions) shares the identical day-granularity semantics and the identical concurrency exposure. Consolidated into one **high-priority Core architecture backlog item** (see the Playbook's own section) rather than patched per-consumer — `GuardianStudent`'s local mutators are explicitly temporary and should be removed once the Core fix ships.

## `person_relationships` — discoverable, not auto-inverted

Stored as one directed row (`person_id`, `related_person_id`, `relationship_type_id`). Proven to be queryable from either side without a second mirrored row. **Deliberately does not** attempt to resolve or deduplicate the inverse direction — recognizing that "uncle" viewed from the other side should read as "nephew," or that a second "sibling" row from the other side restates the same fact, both require relationship_type-level knowledge (which types are symmetric, what a type's inverse label is) that doesn't exist yet. That's Family-tree read-model work the Playbook explicitly defers past this sprint ("a derived read — can be built any time after this sprint").

No `public_id`, no `HasTemporalAssignment` — a shallow generic edge (the same "pivot, not aggregate" treatment as Tags' `taggables`), hard-deleted with Activitylog audit rather than soft-deleted.

## `Household` and `BillingGroup` — two independent shells, not one

The Blueprint's own "Billing Groups / Households" phrasing left genuinely ambiguous whether this is one table or two. Resolved as **two entirely independent administrative shells**:
- `Household` — Person-scoped, general administrative grouping (e.g., a shared residence for mailing/logistics). Never derived from or coupled to `person_relationships`.
- `BillingGroup` — Student-scoped, a pure shell for Finance's future "which students bill together" need. No discount rate, no invoice linkage, no payment allocation — nothing beyond a label and membership.

No FK or query coupling between the two in either direction — proven by a dedicated independence test — so Finance can consume `BillingGroup` later without `Household` ever needing to change.

**Membership pivots are promotion-friendly by construction, verified empirically.** Both `household_members` and `billing_group_members` carry their own `id()` primary key and `timestamps()` — not Laravel's bare default pivot shape — specifically so a future promotion to a first-class model (adding `role`, `joined_at`, an approval workflow, metadata) needs no schema change to the join mechanism itself. Verified directly: a throwaway `Pivot`-extending model was wired onto the existing `household_members` table via `->using()`, against already-attached real data, with zero migration — proving the promotion path works today, not just in theory. **One named, non-blocking exception:** the current unique constraint on the FK pair assumes single-current-membership (join or leave, not a history of periods); genuine multi-period history (left and rejoined, tracked as distinct periods) would need that constraint loosened first — a normal additive migration, recorded in the Playbook's Technical Debt Register, not a blocker to anything built today.

## What this sprint deliberately does not build

Finance consumption of `BillingGroup` (Phase 7). Guardian-verification/step-up-auth UI (Phase 4, alongside Admissions — `verified_by`/`verified_at` exist as schema only). Any Family-tree UI or inverse-relationship-label resolution (a derived read, buildable any time, not gated on anything further). Historical/multi-period membership tracking for Household/BillingGroup (documented future evolution, not a current requirement).
