# BUS-0022: Reception Domain Boundary — Visitor Identity vs. Access Credential, Mail & Correspondence Scope

**Status:** 🟢 Accepted

**Date:** 2026-07-22

**Related Domains:** Reception (new), Smart Campus, HR, Administration, Platform Services

**Related ADRs:** BUS-0020 (same class of naming-collision correction, applied here to "Visitor" instead of "Course"/"Program"/"Department"), BUS-0021 (this ADR groups several tightly-coupled sub-decisions as one, per that rule)

## Context

Raised while designing a new Reception domain, requested by name, following the same review-before-redesign discipline already used for Academic. Smart Campus (already shipped, v3) currently owns a Master Data entity named `Visitor` and a full "Visitor Management" submodule/workflow, which directly overlaps with Reception's proposed responsibility for visitor check-in, registration, and host notification.

## Problem

Does adding Reception require transferring Visitor ownership away from Smart Campus, or was "Visitor" always two distinct concepts sharing one name? Does Mail & Correspondence deserve its own domain, or stay a Reception submodule? What additional Master Data does Reception need?

## Alternatives Considered

- **Framing this as an ownership transfer** ("Visitor moves from Smart Campus to Reception") — the initial proposal, rejected on review. This mischaracterizes the fix: Smart Campus's own stated justification for existing ("a door needs to know who's requesting entry") was always about the *access-granting* side, never the administrative registration side. There was never a legitimate reason for Smart Campus to own visitor identity/purpose/host in the first place — this is the same shape of mistake as Program/Program-of-Study, HR-Department/Academic-Department, and Course/Subject: one word, two concepts, wrongly merged from the start, not a decision to reverse.
- **Splitting Mail & Correspondence into its own domain now** — rejected. No retention-policy, legal-correspondence, or e-signature requirement exists yet to justify independent Domain status, per the same threshold discipline as BUS-0016.
- **Leaving Access Credential's Visitor-binding undocumented** — rejected. Smart Campus's existing Access Credential entity already generalizes across Student/Employee/Visitor; the correct fix clarifies its Visitor binding points at Reception's Visitor record, not a new parallel entity.

## Final Decision

- **Reception** owns `Visitor` (identity: who, why, host, contact info, documents, visit number) and `Visit` (the visit record: entry/exit, status) outright, as new Master Data.
- **Smart Campus** owns only **Access Credential** (already-existing, generalized entity — card/QR/PIN/biometric, time-bounded, Access-Point-scoped) and **Access Events** (the audit trail of actual door/gate transactions) for a Visitor — exactly the same shape already used for Students and Employees: Smart Campus never owned Person identity for either of them, and it shouldn't have owned it for Visitors.
- Smart Campus **consumes** Reception's `Visitor` record to bind an Access Credential when a Visit requires passing a controlled Access Point. No credential exists without a Reception-owned Visitor behind it.
- **Mail & Correspondence** stays a Reception submodule, not a separate domain, with its own independent Master Data (`Correspondence Item`) — reversible later per BUS-0016's threshold discipline if it grows real retention/legal/e-signature requirements.
- Reception gains three additional Master Data entities: **Reception Desk** (which physical desk/location handled a Visit — relevant once a campus has more than one), **Delivery Method** (manual, postal, courier, email), **Correspondence Type** (incoming, outgoing, internal, parcel, document).

## Why This Decision Was Chosen

This is the fourth time in this domain-design pass (after Program, Department, Course) that a naming collision, not a genuine ownership dispute, turned out to be the real problem — recognizing the pattern kept the fix a clarification rather than a migration. It also directly reuses the already-proven "own identity vs. consume identity to bind a credential" shape already established for Student and Employee against Smart Campus; Visitor never needed a different rule.

## Consequences

Easier: Smart Campus's existing, already-generalized Access Credential entity needs no new structure, just a corrected reference target; Reception can be built and reasoned about independently of Smart Campus's device/credential mechanics. Harder: `smart-campus.md`'s already-shipped v3 prose (Master Data, Workflows, Domain Events, Mobile Features, Data Ownership) needs a real edit to remove its standalone `Visitor` entity and re-scope "Visitor Management" to "Visitor Access Control."

## Future Implications

If Mail & Correspondence later grows retention policies, legal-correspondence handling, or e-signature acknowledgment, it splits into its own domain at that point, per BUS-0016 — not before. Whether Reception should subscribe to the cross-cutting Emergency Coordination service (visitor accountability during an active emergency) was raised during design but not decided — tracked as an open item in `reception.md`, not assumed accepted.

## Traceability

- **Business requirement:** front-desk/administrative visitor handling and correspondence routing, without duplicating or fragmenting Smart Campus's physical-security responsibility.
- **Introduced in:** the Reception domain bounded-context review, corrected on the user's own review of the first draft.
- **Depended on by:** `reception.md` (new), `smart-campus.md` (edited), any future Records Management domain if Correspondence eventually splits out.
