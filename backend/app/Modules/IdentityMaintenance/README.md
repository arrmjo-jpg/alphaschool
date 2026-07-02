# Identity Maintenance

**Layer:** Foundation

Person Merge, Duplicate Resolution, Identity Correction (policy/approval layer), Identity Recovery, Person Anonymization. Orchestrates across every Domain module via standard contracts (`ReassignsIdentityReferences`, `RedactsPersonalData`) rather than direct table access — never owns day-to-day Person data, only the rare, high-stakes operations that restructure or prune the identity graph.

See `docs/DOMAIN_BLUEPRINT.md` §1 and Addendum C.

Populated starting Phase 3 — empty as of Sprint 0.1 (placeholder only, no code yet).
