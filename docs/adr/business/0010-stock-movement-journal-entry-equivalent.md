# BUS-0010: Stock Movement Plays the Same Architectural Role in Inventory That Journal Entry Plays in Accounting

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Inventory, Accounting (integration point for Inventory Valuation)

**Related ADRs:** BUS-0009 (Tracking Strategy, mechanically realized through Movement's Serial Number requirement)

## Context

A "Stock Management" layer was proposed as the shared operational engine beneath Student Inventory, Custody, and Consumables — Stock Movement, Ledger, Balance, Reservation, Allocation, and related concepts — explicitly not a fourth inventory category.

## Problem

Should business workflows (Issue, Return, Consumption) be allowed to write stock quantities directly, or must every quantity change go through a formal transaction record?

## Alternatives Considered

- **Workflows write quantities directly, no intermediate ledger** — the implicit shape of the original three-lifecycle design before this layer was proposed. Rejected: no audit trail of *how* a balance reached its current state, no point-in-time reconstruction, and no consistent reconciliation mechanism across three otherwise-independent workflows.
- **A per-lifecycle-category ledger (three separate movement logs)** — rejected: recreates duplicated machinery for what is structurally one problem, and breaks cross-category reporting (a single Item Catalog entry could theoretically appear in more than one movement log).
- **One unified Stock Movement/Ledger/Balance mechanism, shared by all three categories, modeled explicitly on Accounting's Journal Entry pattern** — accepted.

## Final Decision

No business workflow writes a stock quantity directly. Every quantity-affecting event creates a Stock Movement (Item, Location, Quantity, Movement Type, a reference back to its causing business record, timestamp, actor). Stock Balance (Current, Available, Reserved, In Transit, On Hold, Damaged, Expired) is a derived, cached aggregate of Movements, never an independently-writable field — the same relationship an Account Balance has to Journal Entries.

## Why This Decision Was Chosen

Structurally identical to a pattern already proven and frozen for Accounting: workflows never write balances directly, balances are computed aggregates of immutable records, and the ledger enables point-in-time reconstruction. Extending a proven pattern rather than inventing a new discipline for Inventory specifically.

## Consequences

Easier: complete, immutable audit trail for every stock change regardless of which of the three lifecycle workflows caused it; Physical/Cycle Count reconciliation becomes a single mechanism (compare counted quantity to Ledger-derived Balance, raise an `ADJUSTMENT` Movement for variance) instead of three separate ones. Harder: every one of the three lifecycle workflows now has an additional required step (produce a Movement) that wasn't explicit before this layer existed.

## Future Implications

For valued stock, `RECEIVE`/`CONSUME`/`WRITE_OFF` Movements can generate corresponding Accounting Journal Entries — two domain-specific ledgers, connected by events, not merged, the same discipline already used for Emergency Coordination and LMS-to-Academic gradebook sync.

## Traceability

- **Business requirement:** "business workflows must never modify stock directly... so that a complete immutable inventory ledger exists," stated as an explicit architectural rule during Inventory's design.
- **Introduced in:** the Inventory Domain Revision turn proposing a Stock Management layer; refined across two immediate follow-up turns (Damaged/Expired quantities, Inventory Snapshot, Inventory Availability).
- **Depended on by:** BUS-0009; any future Accounting integration for Inventory Valuation.
