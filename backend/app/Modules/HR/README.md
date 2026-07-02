# HR

**Layer:** Domain

Employee lifecycle: hiring, Employment periods (mirrors Enrollment — never fields flatly on Employee), Position history, Salary history, branch membership, leave, retirement/resignation.

**Rule:** Domain modules never import a sibling Domain module directly — only via events or that module's public service contract. See `docs/DOMAIN_BLUEPRINT.md` §1, §10, Addendum B2.

Populated starting Phase 6 — empty as of Sprint 0.1 (placeholder only, no code yet).
