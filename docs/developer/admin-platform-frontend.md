# Admin Platform Foundation (frontend)

**Status:** Complete, frozen as `v1.0-admin-platform-foundation`. Governed by `docs/ADMIN_PLATFORM.md` and `docs/adr/0015-admin-platform-foundation-frontend-architecture.md`.

## What this is

The reusable `admin/` React shell every future business workspace (Identity, Students, Admissions, Academic, HR, Finance, …) builds on top of. Zero business content ships here — see ADR-0015 Decision 2.

## The extension point

A workspace becomes installable by adding exactly one `WorkspaceDefinition` (`src/platform/navigation/workspace-definition.ts`) to `src/workspaces/registry.ts`:

```ts
registerWorkspace({
  key: 'students',
  labelKey: 'workspaces.students.label',
  icon: GraduationCap,
  requiredPermission: 'students.view',
  navItems: [{ key: 'roster', labelKey: 'workspaces.students.roster', icon: List, path: '' }],
  loadComponent: () => import('./students/root'),
})
```

Nothing in `src/platform` is touched. The workspace mounts under the generic `/w/$workspaceKey` route (`src/platform/shell/workspace-route-page.tsx`), lazy-loaded so an unregistered/unlicensed workspace never ships in a user's bundle. Visibility is the intersection of this local registry and `GET /api/v1/workspaces`'s server-computed response (`src/platform/navigation/use-visible-workspaces.ts`) — the server decides *which* keys a user may see (from Permission Groups, never client-side), the registry decides *how* a permitted key renders. Proven by `src/test/extension-point.test.tsx`: registering a synthetic workspace and granting/withholding its key from a mocked server response, with zero edits to any platform source file.

## Backend surfaces this depends on

`GET /api/v1/me` (current user + the union of permission names across every branch-scoped role — coarse nav-gating only, real authorization stays each endpoint's own Policy) and `GET /api/v1/workspaces` (returns `[]` until a real workspace registers a permission mapping in `App\Modules\Identity\Services\WorkspaceAccessResolver`). Both live in Identity, added ahead of the frontend as ADR-0015's prerequisite slice.

## The six frameworks

| Framework | Location | Contract |
|---|---|---|
| Dashboard | `src/platform/dashboard` | A workspace supplies an ordered `WidgetDefinition[]`; renders a responsive grid. Per-user layout persistence deferred (no generic preferences store exists yet — Administration Platform, ADR-0011) |
| Widget | `src/platform/widgets` | `{ id, titleKey, requiredPermission, dataSource, render }` — a widget declares which endpoint to call, never its own aggregation logic. `createKpiWidget` is the one generic implementation shipped |
| DataTable | `src/platform/data-table` | `useServerDataTable` — manual pagination/sorting against Laravel's standard paginated-resource shape (`data`/`meta.current_page`/`last_page`/`per_page`/`total`); loading/error/empty states and pagination controls are the framework's job |
| Form | `src/platform/forms` | React Hook Form + Zod; `TextField`/`SelectField`/`DateField`/`BilingualNameField` (mirrors the backend's `*_en`/`*_ar` column-pair convention directly); `mapServerErrors()` translates Laravel's `{ errors: { field: [msg] } }` 422 shape into RHF field errors |
| Modal | `src/platform/modals` | `useModalStore` (imperative stacking) + `useConfirm()` (promise-based ConfirmDialog) + `Sheet` (the mobile drawer fallback) |
| Extension points | `src/workspaces/registry.ts`, `src/platform/search/search-provider.ts`, `src/platform/command-palette/command-registry.ts`, `src/platform/notifications/types.ts` | Registration functions a workspace calls; nothing here is workspace-specific |

## Search, command palette, notifications — contracts only

No backend exists yet for any of these (Scout-based search, the Notification Engine ADR-0013, Reporting, Broadcasting — ADR-0015 Decision 6). `SearchProvider`/`NotificationProvider` are real TypeScript interfaces with a mock implementation each; `SearchBar` and the `cmdk`-based command palette both consume the same `searchAllProviders()` registry. Notifications poll every 30s rather than subscribing to a realtime channel. Swapping a mock for a real provider is additive — no component changes.

## Theme system

CSS variables in `src/index.css`, mapped through Tailwind's `@theme inline`. `--primary` is the one token a dedicated-instance customer's branding (ADR-0006) is expected to override (`setBrandPrimaryColor()`); every other token stays fixed across deployments. Light/dark via `useThemeStore` setting `data-theme` on `<html>`, falling back to `prefers-color-scheme` when unset.

## i18n / RTL

`i18next` + `react-i18next`, `en`/`ar` seeded now. `setLocale()` flips `document.documentElement.dir`; every platform component uses Tailwind logical properties (`ps-*`/`pe-*`/`start-*`/`end-*`) rather than `left`/`right`, so RTL is structural, not a mirrored stylesheet. A workspace registers its own translations via `registerWorkspaceTranslations(namespace, locale, resources)` without touching `src/platform/i18n`.

## Dev-only harness

`src/dev/dev-harness.tsx`, mounted at `/dev/harness` only when `import.meta.env.DEV` is true (`src/platform/routing/router.tsx` — the whole branch, including the dynamic import, is dead-code-eliminated from production builds; verified by building and grepping `dist/` for the harness's own text). Proves DataTable/Form/Modal/Widget end-to-end against **fixture data** (`src/dev/mock-data.ts`), not a real Identity endpoint — no Roles/Branches/Permissions list API exists on the backend yet, so this deliberately avoids inventing new backend surface beyond ADR-0015's agreed prerequisite slice. Never registered in `workspaces/registry.ts`; never appears in navigation.

## What this milestone deliberately does not build

Any business workspace. Drag/resize dashboard layout persistence. Real-time notifications (depends on a Broadcasting connection not yet configured). Real search (depends on the backend Scout abstraction, Addendum D5). A branch/team switcher (`/me`'s permission set is the union across all branches instead — see ADR-0015's Alternatives Considered).
