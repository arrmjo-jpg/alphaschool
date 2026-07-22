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

## Standing rule: design as a commercial, multi-institution product (binding, added 2026-07-22)

**Treat AlphaSchool as a commercial ERP platform, not a custom school project.** Every domain must be designed as reusable, configurable, and extensible enough to support different educational institutions, countries, regulations, and third-party integrations without architectural changes. Never bake in an assumption specific to one customer, one country's regulatory regime, or one vendor. Anything that genuinely varies by deployment — a Ministry of Health API, an insurance provider, a payment gateway, a regulatory data-retention period — is a configurable Provider Slot (`DeclaresProviderSlots`) or a per-deployment setting, never a hardcoded integration or a fact assumed true everywhere. Before designing any domain that touches an external system or a jurisdiction-specific rule, ask explicitly: does this vary by country/regulator/vendor? If yes, it's a slot or a setting, not a decision baked into the domain's own logic.

## Standing rule: the platform extends beyond administrative software (binding, added 2026-07-22)

**Do not stop at traditional ERP modules.** Think about the entire educational institution ecosystem, including Operational Technology (bell systems, PA, emergency response, digital signage, IoT device fleets) and future smart-campus capabilities (access control, CCTV, energy management) — not only administrative/informational processes (records, finance, academics). AlphaSchool should become the operating platform for the whole institution, its physical operation as much as its paperwork, not only its administrative processes. Domains that control physical hardware get modeled with the same Provider Slot discipline as any software integration (per the standing rule above), but carry an additional design obligation ordinary IT domains don't: command priority/preemption for safety-critical overrides, and offline/degraded-mode behavior for anything life-safety-related — a system that only works when the cloud is reachable is a liability, not a feature, for a fire alarm.

## Standing rule: document Commercial Differentiators for every domain (binding, added 2026-07-22)

We are no longer documenting individual modules only. From now on, whenever a new domain is documented, think about what would make AlphaSchool commercially stronger than competing education ERPs — not only what schools need today, but what will make the product competitive over the next decade. Every domain gets a closing **Commercial Differentiators** section: capabilities that could become genuine unique selling points, marketplace extensions, premium/enterprise-tier features, or a real procurement advantage over locked-in, single-vendor, single-country competitors. This is a business-positioning exercise, not a feature restatement — if a Commercial Differentiator just repeats something already listed under Business Capabilities, it isn't one.

## Standing rule: documentation-first architecture (binding, added 2026-07-22)

**No architectural knowledge may exist exclusively in conversation. Every accepted decision must be reflected in documentation before moving to the next topic.** Conversation is temporary; documentation is permanent — if something exists only in chat, assume it will be forgotten. This governs `docs/BUSINESS_BLUEPRINT.md` and its decision trail specifically, alongside the existing `docs/DOMAIN_BLUEPRINT.md`/`docs/adr/*` discipline for the frozen backend:

- **Decision Log & ADRs.** Every significant discussion gets a full ADR in `docs/adr/business/` (template at `docs/adr/business/template.md`; index in `docs/BUSINESS_BLUEPRINT.md`'s Governance section) — Decision ID, Title, Context, Problem, Alternatives Considered, Final Decision, Why, Consequences, Future Implications, Related Domains, Related ADRs, Traceability. This is a separate numbered track from `docs/adr/*`'s backend ADRs, not merged into it.
- **Architecture Status** — every entity/decision carries one of 🟢 Accepted, 🟡 Proposed, 🔵 Deferred, 🔴 Rejected, ⚪ Research Required (legend in `docs/BUSINESS_BLUEPRINT.md`), specifically so a settled topic is never re-litigated from scratch a month later.
- **Traceability.** For every entity: why it exists, which requirement created it, what depends on it, what will reuse it, which discussion introduced it.
- **Cross-references are never implicit.** Introducing a new entity requires searching the existing documentation for domains that should reference it, and adding that reference immediately, not leaving it to be discovered later.
- **Deferred features get a real record, not the word "future."** Reason for postponing, whether the architectural seam is already reserved, whether migration will be needed later, expected phase, dependencies.
- **Open Architecture Questions** is a permanent section in `docs/BUSINESS_BLUEPRINT.md` — unresolved topics live there until resolved, at which point they move into their own ADR. Never silently deleted.
- **Architecture Assumptions** are recorded explicitly when the agent assumes something rather than confirms it; if later disproven, the assumption is replaced by a new ADR documenting the correction, not quietly edited away.
- **Consistency validation before any new design**: search the existing documentation first — does the proposal contradict a prior ADR, duplicate an existing entity, cross a domain boundary, or violate an already-accepted pattern? If a conflict is found, stop and document the conflict before continuing, rather than resolving it silently.

## Working conventions already established in this project

- Docker Compose is the only supported dev environment (`docker compose -p alphaschool exec <service> ...`); never run backend/frontend tooling directly on the host.
- Every implementation phase follows: Design → Implementation → Live Verification → Fixture Revert (if a temporary fixture was used) → Documentation → Commit.
- New workspaces/providers/settings are verified live against a temporary, fully-reverted fixture before the real backend integration exists, then re-verified against the real adapter once it does (see Phase E-A/E-B and F-A/F-B for the reference shape).
- Stop and surface any discovered architectural gap or contradiction to the user before implementing a workaround — do not silently resolve it.
- Forward-only Git history: never rewrite, amend published commits, or force-push without explicit instruction.
- **Before any major implementation phase, verify the test environment is isolated from the development database** (Definition of Done item 16, `docs/IMPLEMENTATION_PLAYBOOK.md`). `backend/tests/TestCase.php` enforces this automatically — it throws immediately if a test run ever resolves to a real database instead of the isolated `sqlite`/`:memory:` connection — but if `phpunit.xml` or `TestCase.php` are ever edited, re-verify live: seed the dev database, run one test, confirm its row count is unaffected. Added 2026-07-21 after `php artisan test` silently wiped the real dev database on every run for a full day; see `docs/developer/rca-2026-07-21-test-database-wipe.md`.
