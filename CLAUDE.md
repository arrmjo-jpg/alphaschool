# AlphaSchool ERP — Project Instructions

This is a multi-year enterprise ERP project run under a strict "prove it, don't assume it" discipline: live verification over claims, explicit architectural review before implementation, forward-only Git history, no speculative abstraction.

## Start here

Before doing any work in this repo, read (in this order):
1. `docs/DOMAIN_BLUEPRINT.md` — the frozen backend domain architecture. Law; not redesigned casually.
2. `docs/IMPLEMENTATION_PLAYBOOK.md` — backend Phase sequence, engineering discipline, Definition of Done, and the narrative history of what's been built and verified.
3. `docs/ADMIN_DESIGN_SYSTEM.md` — the frozen admin frontend design system, including per-workspace design sections (e.g. §26 Configuration Platform, §27 Provider Registry).
4. `docs/adr/*` — individual architectural decisions, each with its own reasoning.
5. `CHANGELOG.md` — chronological summary of everything shipped.

These documents are the project's source of truth — not prior conversations. Treat any prior chat transcript as ephemeral working notes; if it conflicts with the current docs, the docs win.

## Standing rule: hand-off-ready documentation (binding, added 2026-07-21)

**Every completed phase or sub-phase must leave the project in a state where a new agent with zero prior conversation history can read the documentation alone and correctly continue the work — no additional explanation from the user required.**

This is enforced as Definition of Done item 15 in `docs/IMPLEMENTATION_PLAYBOOK.md`'s "Global Engineering Discipline" section — see that document's **Hand-off Documentation Standard** for the full binding checklist (architectural decisions and reasoning, what was implemented, what was intentionally not implemented and why, new/modified contracts, public APIs/interfaces/DTOs/extension points, verification actually performed, known limitations, the exact next step, and any new conventions). Do not consider a phase done until that checklist is satisfied in the relevant docs (the phase's own Playbook/Design System entry, plus `CHANGELOG.md`), not just in the chat response to the user.

## Working conventions already established in this project

- Docker Compose is the only supported dev environment (`docker compose -p alphaschool exec <service> ...`); never run backend/frontend tooling directly on the host.
- Every implementation phase follows: Design → Implementation → Live Verification → Fixture Revert (if a temporary fixture was used) → Documentation → Commit.
- New workspaces/providers/settings are verified live against a temporary, fully-reverted fixture before the real backend integration exists, then re-verified against the real adapter once it does (see Phase E-A/E-B and F-A/F-B for the reference shape).
- Stop and surface any discovered architectural gap or contradiction to the user before implementing a workaround — do not silently resolve it.
- Forward-only Git history: never rewrite, amend published commits, or force-push without explicit instruction.
