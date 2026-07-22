# BUS-0009: Tracking Strategy (Pool vs. Serialized) Is a Setting With an Item Catalog Override, Never a Classification Input

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Inventory

**Related ADRs:** BUS-0008 (the classification test this decision keeps clean)

## Context

Whether an item is tracked as an interchangeable pool or as individually-serialized units was originally used as part of the Custody-vs-Student-Inventory classification test (see BUS-0008's first correction). That was found to be wrong: tracking granularity is a school-configurable enforcement choice, not a fixed property of the item.

## Problem

Given tracking granularity is real and needs to be modeled somewhere, but must not be a classification input, where does it live?

## Alternatives Considered

- **A new "Inventory Policy" concept, separate from both Settings and Master Data** — considered and rejected as an unnecessary third mechanism; the platform already has a proven shape for exactly this need.
- **A single deployment-wide flag with no per-item override** — rejected: some specific items genuinely need to diverge from the deployment's default (a specific regulated textbook edition tracked individually while the rest of the catalog is pooled).
- **A Setting (deployment-wide default per lifecycle category) with an Item Catalog field as the per-item override** — accepted.

## Final Decision

Tracking Strategy default is a Domain Configuration Setting, resolved per lifecycle category. The Item Catalog entry for any specific item carries an optional override field. This is the identical default-then-override shape `SettingsResolver`'s org/branch altitude chain already uses, applied at item-type granularity instead of branch granularity — not a new mechanism.

## Why This Decision Was Chosen

Reuses infrastructure that already exists and is already proven, rather than inventing a parallel "policy" concept with its own resolution logic. Also mechanically realizes Batch/Lot/Serial Number Management (BUS-0010's Stock Management layer) — whether a Movement requires a Serial Number is a direct read of this Setting/override.

## Consequences

Easier: no new resolution mechanism to build or explain; consistent with every other resolvable rule in this document. Harder: none identified — this is a strict simplification relative to inventing a new mechanism.

## Future Implications

Any future domain needing a similarly-shaped "deployment default, per-item override" rule should reuse this same pattern rather than inventing its own.

## Traceability

- **Business requirement:** support schools with different enforcement policies (serialize every textbook vs. treat them as a pool) without hardcoding either choice.
- **Introduced in:** the Inventory classification-test-revision turn, as the direct resolution of the error found in BUS-0008.
- **Depended on by:** BUS-0010 (Batch/Lot/Serial Number Management reads this Setting to decide whether a Movement requires a serial number).
