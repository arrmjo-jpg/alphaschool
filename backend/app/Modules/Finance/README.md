# Finance

**Layer:** Domain

Invoices, Journals, Cashboxes, Fee Plans, Billing Policies (Sibling/Employee Discount, Scholarship, Late Fee, Installment), consumption of Household data via People's public service, payment processing.

**Rule:** Domain modules never import a sibling Domain module directly — only via events or that module's public service contract. Finance is the deliberate aggregator exception for *reading* other modules' data, but still only through their public services. See `docs/DOMAIN_BLUEPRINT.md` §1, §2.

Populated starting Phase 7 — empty as of Sprint 0.1 (placeholder only, no code yet).
