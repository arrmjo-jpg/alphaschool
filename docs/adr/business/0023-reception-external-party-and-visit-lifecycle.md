# BUS-0023: Reception Refinements — External Party, Polymorphic Correspondence Routing, Full Visit Lifecycle

**Status:** 🟢 Accepted (External Party itself is 🟡 Proposed — see Final Decision)

**Date:** 2026-07-22

**Related Domains:** Reception, HR (Employee/Department remains the primary routing target)

**Related ADRs:** BUS-0022 (Reception's domain boundary — this ADR only refines Reception's own internal completeness, not its boundary with Smart Campus, so it stays separate per BUS-0021's coupling test)

## Context

Raised in a post-ship review of `reception.md`, three gaps were found: recurring external correspondents (a Ministry, a shipping company, a bank, a supplier, a partner university) would otherwise accumulate as inconsistent free text; Correspondence routing was documented as resolving only to an Employee, which doesn't match how internal mail actually gets addressed; and the Visitor workflow stopped documenting at check-in, without the full lifecycle a real visit goes through (escort, group visits, equipment leaving with a visitor, badge-return verification).

## Problem

Should recurring external correspondents get their own Master Data, should Correspondence routing support recipient types beyond Employee, and should the Visit workflow be documented end-to-end?

## Alternatives Considered

- **Leave external correspondents as free text only** — rejected as the default going forward; a Ministry of Education or a recurring shipping vendor written differently every time defeats reporting and search, the same problem that motivates every other Master Data entity in this platform. But free text isn't removed — a one-off, never-repeated sender doesn't deserve a catalog entry either.
- **Keep Correspondence routing Employee-only** — rejected; a piece of mail addressed to "the Accounting Department" or a future Business Unit shouldn't be forced to resolve to one arbitrary Employee.
- **Treat the fuller Visit lifecycle as new features requiring new Domain Events** — rejected; check-out, escort, and badge-return verification were already implied by the existing `VisitorCheckedOut` event and Visit's own Master Data fields — this is a documentation completeness fix, not new capability.

## Final Decision

- **External Party** (🟡 Proposed — a genuinely useful addition, not yet battle-tested against a real deployment's correspondence volume): new, optional Master Data — Type, Name, Contact Information. `Correspondence Item` may reference an External Party **or** fall back to free text for a one-off/unlisted sender; free text is never removed as an option.
- **Correspondence Item's recipient/sender is polymorphic** (🟢 Accepted): Employee, Department, a future Domain/Business Unit reference (seam reserved, not built), or an External Party — routing is never assumed to resolve to a single Employee.
- **Full Visit lifecycle documented end-to-end** (🟢 Accepted): Visit Created → Check-in → Host Notification → (optional) Access Credential issuance by Smart Campus → Check-out (covering escorting-employee sign-off, group-visit checkout, equipment/items leaving with the visitor, badge-return verification) → Visit Closed. No new Domain Events — this refines the existing `VisitorCheckedOut` step's documentation, not the event model.

## Why This Decision Was Chosen

External Party reuses the same "don't let a free-text field silently duplicate a real-world identity dozens of ways" reasoning already applied throughout this platform (the same instinct behind every other Master Data entity here), while explicitly keeping free text available rather than forcing every correspondent into a catalog. Polymorphic routing and the full Visit lifecycle are both completeness fixes to what Reception already implied, not new architectural surface.

## Consequences

Easier: correspondence reporting can group by a consistent External Party identity instead of reconciling name variants; Correspondence routing doesn't force an artificial single-Employee target; the Visit workflow reads as a complete process instead of stopping at arrival. Harder: nothing new — External Party is additive and optional, and the other two are documentation-only changes.

## Future Implications

If External Party proves out, it's a natural candidate for cross-domain reuse later (Procurement's suppliers, Accounting's external payees) rather than staying Reception-only — not decided now, just a plausible future seam.

## Traceability

- **Business requirement:** consistent, reportable correspondence handling as visit/mail volume grows.
- **Introduced in:** post-ship review of `reception.md`.
- **Depended on by:** none yet — purely additive to Reception's existing Master Data and workflow prose.
