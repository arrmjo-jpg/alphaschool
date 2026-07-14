# ADR-0015: Admin Platform Foundation — Frontend Architecture

**Status:** Accepted

**Date:** 2026-07-13

## Context

`docs/ADMIN_PLATFORM.md` (frozen 2026-07-02) already named the Workspace-based admin concept and explicitly called for building "the reusable Workspace shell pattern once... as a shared Admin Platform component" before any individual workspace is populated. Until now no sprint had claimed that work, no frontend technology stack had been chosen anywhere in the frozen docs, and the `admin/` directory was empty. This ADR freezes the concrete architecture for that shell — the **Admin Platform Foundation** — before implementation begins.

This is unrelated to ADR-0011's backend "Administration Platform" bounded context (`App\Modules\Administration\*` — Settings, Custom Fields, Favorites/Tags, Audit, Import/Export, Licensing). That naming collision is already resolved by ADR-0011's own convention (backend: "Administration Platform," frontend: "Admin Platform") and this ADR does not disturb it.

## Decision

**1. Technology stack, frozen for the `admin/` application:** React + Vite + TypeScript (strict), Tailwind CSS, shadcn/ui (Radix primitives, vendored not npm-installed), TanStack Router, TanStack Query, TanStack Table, React Hook Form + Zod, Zustand (chrome state only — most state is server state via Query), i18next (bilingual `en`/`ar`, RTL-first). `frontend/` (the separate Next.js guardian/student portal) is unaffected and remains a distinct stack decision.

> **Implementation note (2026-07-13):** this Decision originally specified React 18. At scaffold time `npm create vite@latest` provisioned React 19 (the current stable release; React 18 is no longer what current tooling produces) alongside Vite 8 and TypeScript 6. No architectural consequence — nothing else in this Decision depended on the React major version — so the implementation proceeded on React 19 rather than pinning an already-superseded version. Recorded here rather than silently drifting from the frozen text.

**2. The Admin Platform Foundation is completely business-agnostic.** No Identity screens, no Roles/Branches/Permissions workspace, no CRUD pages, no navigation entry for anything but the shell's own chrome. It provides infrastructure only — the frontend counterpart to Core/Foundation never containing Domain business logic.

**3. Existing Identity endpoints (Roles, Permissions, Branches) may be used strictly as temporary development/demo data sources** to prove the DataTable, Form, Modal, and Widget frameworks work against real API responses. These call sites live in a clearly separated dev-only harness, are never registered in `workspaces/registry.ts`, never appear in production navigation, and are excluded from the production build.

> **Implementation note (2026-07-13):** no Roles/Branches/Permissions list endpoint actually exists on the backend today (confirmed by inspection of `routes/api.php`) — only `/login`, `/logout`, `/me`, `/workspaces`, and `/merge-requests/*`. Building one would be new backend surface beyond ADR-0015 Decision 7's agreed prerequisite slice, so the dev harness instead proves these four frameworks against a realistic, Laravel-shaped **fixture** dataset (`admin/src/dev/mock-data.ts`). Swapping the fixture for a real endpoint later is a one-line `queryFn` change in the harness, nothing structural.

**4. The workspace extension point is a hard contract, not a convention.** A future business module must become installable by registering exactly one `WorkspaceDefinition` object. No change to `AppShell`, the navigation renderer, the router root, or the layout system is permitted for a module to appear. This is enforced by an automated test that registers a synthetic, test-only workspace at runtime and asserts it renders in nav/routing without any modification to platform source — the frontend equivalent of the backend's contract-declaration scanners (Sprint 3.1).

**5. Zero-workspace is the primary acceptance state, not an edge case.** The shell must render a correct, intentional empty/licensing-pending state with nothing registered. This is the actual proof the abstraction holds.

**6. Notifications, Search, Reporting, and Broadcasting backends are explicitly not started by this milestone.** Only their frontend contracts (`SearchProvider`, `NotificationProvider`, `WidgetDefinition`'s data-source shape) and UI shells ship now, backed by mock/in-memory providers in development. Real backends (Notification Engine, Scout search, Reporting, a broadcasting connection) are future, independently-scoped work; swapping a mock provider for a real one must require no change to any platform component, mirroring the Channel/Provider isolation discipline already frozen for the backend Notification Engine (ADR-0013).

**7. A small backend prerequisite slice ships first, inside Identity (Foundation, already frozen), before frontend shell work begins:** `GET /api/v1/me` (current user + resolved permission set, union across all branch-scoped roles — coarse nav-gating only; real authorization remains each endpoint's own Policy, exactly as `docs/ADMIN_PLATFORM.md` already requires), `GET /api/v1/workspaces` (server-computed, returns `[]` today since nothing is registered — the endpoint is real and versioned now, logic is added additively once a real workspace exists), and `config/cors.php` (published and configured for the SPA's dev origin; no new architecture — bearer-token auth per `AuthController`'s existing docblock, so `supports_credentials` stays `false`, no Sanctum stateful-domain/cookie concept introduced).

## Consequences

Every future business workspace (Identity, Students, Admissions, Academic, HR, Finance, …) is built entirely inside `workspaces/`, composing the frameworks this milestone ships, with zero platform-layer changes — the same guarantee the backend's Core/Foundation contracts give Domain modules. The stack choice is now binding for all future Admin Platform frontend work; changing it later requires a superseding ADR, not an ad hoc substitution. `/api/v1/workspaces` returning `[]` is expected and correct until the first real workspace ships — this is not a bug to "fix" prematurely.

## Alternatives Considered

- **Build the first real workspace (e.g., Identity) alongside the shell, to prove it against a real feature immediately.** Rejected — this is exactly the "prediction, not promotion" mistake `docs/ADMIN_PLATFORM.md`'s own Sequencing section already warns against; the shell must be proven by a synthetic extension-point test, not by shipping business content early.
- **Defer `/api/v1/me` and `/api/v1/workspaces` until a real workspace needs them.** Rejected — the shell cannot demonstrate its own primary acceptance criterion (correct zero-workspace behavior against a real authenticated session) without them; they are infrastructure the shell is structurally blocked without, not speculative scope.
- **Introduce a "current branch" / team-switcher concept now to make `/me`'s permission resolution branch-precise.** Rejected — this is a business/UX concept (branch switching) the Foundation milestone has no mandate to invent; `/me` instead returns the union of permissions across every branch-scoped role the user holds, sufficient for coarse nav-gating, with real per-action authorization staying at each endpoint's own Policy regardless.

## References

`docs/ADMIN_PLATFORM.md` (companion frontend architecture document, this ADR's direct parent). `docs/adr/0011-administration-platform-bounded-context.md` (naming disambiguation), `docs/adr/0013-channel-provider-separation-for-notification-engine.md` (the Provider-isolation discipline this ADR's Decision 6 mirrors). `docs/DOMAIN_BLUEPRINT.md` §8 (Permission Groups, roles never granted directly to a user).
