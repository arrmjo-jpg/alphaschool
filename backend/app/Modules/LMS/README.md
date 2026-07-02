# LMS

**Layer:** Domain

Learning content, course materials, videos.

**Naming note:** an "assignment" in LMS means homework/coursework — a different concept from the Assignment Engine pattern in Core. Keep this distinction explicit in code (e.g. `CourseAssignment`, not `Assignment`) to avoid confusion with `App\Core`'s temporal Assignment pattern.

**Rule:** Domain modules never import a sibling Domain module directly — only via events or that module's public service contract. See `docs/DOMAIN_BLUEPRINT.md` §1.

Populated starting Phase 8 — empty as of Sprint 0.1 (placeholder only, no code yet).
