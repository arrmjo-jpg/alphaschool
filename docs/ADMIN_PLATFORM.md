# AlphaSchool ERP — Admin Platform Architecture

**Status:** FROZEN / OFFICIAL — adopted 2026-07-02 as the formal companion document to `docs/DOMAIN_BLUEPRINT.md`. This is the **sole architectural reference for building the React Admin application**, exactly as `docs/DOMAIN_BLUEPRINT.md` is the sole reference for backend domain architecture. It carries the same governance weight: no redesign without an approved ADR (see `docs/adr/`). It remains a UX/Platform decision, deliberately separate in scope from the Domain Blueprint — it does not add, change, or redesign any domain module, aggregate, or backend rule. When `docs/DOMAIN_BLUEPRINT.md`'s planned documentation split (Addendum D6) is executed, this file's natural home is a new `docs/admin-platform/` category, parallel to `architecture/`, `foundation/`, and `domain/`.

## Core decision: Workspace-based Admin

AlphaSchool's admin experience is organized as a set of **Workspaces**, not one flat dashboard with a global navigation tree. Each major business area (Identity, Admissions, Academic, Students, HR, Finance, Inventory, Library, Transportation, LMS, CRM, Reporting, Maintenance) is its own self-contained working environment with its own dashboard, navigation, widgets, KPIs, notifications, search scope, quick actions, favorites, and recent activity — the goal being a product that feels like Dynamics/SAP/Odoo, not a traditional CRUD admin panel.

## Why this isn't a new architectural concept — it's a projection of existing ones

A Workspace is the presentation-layer counterpart to the already-decided **Permission Groups** (`docs/DOMAIN_BLUEPRINT.md` §8). "Which workspaces can this user see" is a query over data that already exists (a role's Permission Group grants) — not a new authorization mechanism. This is the reasoning behind every decision below: **Workspace stays a thin, dumb presentation shell that delegates to systems already decided, and never reimplements them** — the same discipline already applied to keep Media and Family from becoming God Objects.

## Layering: where Workspace lives

Workspace does not fit into the Core/Foundation/Domain layering (`docs/DOMAIN_BLUEPRINT.md` §2) — that layering governs backend business-data ownership, and a Workspace has no lifecycle, no history, and needs none of the temporal pattern. It is **not** a Domain module, not an aggregate, and does not live under `app/Modules/*`.

The one backend surface required: an endpoint (e.g. `/api/v1/workspaces`) returning which workspace definitions the current user can access, computed server-side from Permission Groups — never decided client-side, since real enforcement stays at each API endpoint's own Policy. This requires no new backend concept.

## Registry facets — what's frontend, what delegates to backend

| Facet | Home | Notes |
|---|---|---|
| Navigation, layout, routes | Admin frontend | Each workspace is its own folder, self-registering nav/routes; own lazy-loaded route bundle |
| Permissions gating | Backend — Permission Groups | Already decided; the registry consumes the result, never re-implements gating |
| Dashboard widgets / KPIs | Backend Reporting engine | A KPI widget is a small, real-time report — the registry declares which report endpoint to call, not its own calculation logic |
| Search providers | Backend Search abstraction (Scout, Blueprint Addendum D5) | Each workspace declares a scope key; backend exposes scoped search per key. Distinct from the Duplicate-Detection service — do not conflate |
| Notifications | Backend Notification Engine | Notifications are tagged by category; the registry maps categories to the workspace they surface in |
| Favorites | Backend — generic `favorites` polymorphic pivot | Same shallow/generic pattern as Tags/Notes (Addendum D2/D3) — one mechanism, filtered per workspace in the UI, not duplicated per workspace |
| Recent Activity | Backend — filtered view over the Audit Engine's per-entity timeline (Addendum A7/C12) | Not a new mechanism — a scoped query over data that already exists |

## Workspace boundaries do not have to mirror Domain module boundaries

A Workspace may compose multiple backend modules' public APIs into one screen organized around a user's job function, not the Blueprint's module list. Example: a "Students Workspace" legitimately spans People (Student, Guardian) and Academic (Enrollment, Attendance, Grades) — this is normal frontend composition of multiple backend public APIs, not a violation of the backend module-boundary rule, which governs backend-to-backend calls only.

## Multi-tenant configurability (per customer instance)

Workspace enablement is driven by the same "configuration, never a fork" principle already applied throughout the backend architecture:

1. **Organization-level licensing** (`docs/DOMAIN_BLUEPRINT.md` Addendum A2/B) determines which workspaces are even possible for a given customer instance.
2. **User-level permissions** (Permission Groups) then filter which of those licensed workspaces a specific user can see.

**Security requirement, not just a UX nicety:** licensing must be enforced at the API layer, not only by hiding a workspace in the UI. A customer who hasn't licensed Finance must not be able to reach Finance's endpoints directly. Recommend a licensing-gate middleware (`EnsureModuleIsLicensed`) checked against Organization's flags on every Domain module route, as defense in depth alongside UI-level hiding.

## Sequencing — what NOT to build yet

Do not scaffold all thirteen workspaces speculatively. Build the **reusable Workspace shell pattern once** (generic layout: nav + dashboard + widgets + search + quick actions + favorites + recent activity, as a shared Admin Platform component) — then populate individual workspaces only as their corresponding backend Domain module actually ships, mirroring `docs/IMPLEMENTATION_PLAYBOOK.md`'s phased rollout exactly:

| Workspace | Arrives with |
|---|---|
| Identity | Phase 2 |
| Students (People + Academic composed) | Phase 2 / Phase 4 (Enrollment) |
| Admissions | Phase 4 |
| Academic | Phase 5 |
| HR | Phase 6 |
| Finance | Phase 7 |
| Inventory, Library, Transportation, LMS, Reporting | Phase 8 (parallelizable, matching backend) |
| CRM, Maintenance | Phase 9 (pending their own architecture design sessions, like Family received) |

Building the shell speculatively is fine and cheap (it's a reusable layout pattern, low risk of getting the abstraction wrong — same reasoning as Tags/Notes). Populating a specific workspace's content ahead of its backend module is the "prediction, not promotion" mistake this project has consistently avoided elsewhere.
