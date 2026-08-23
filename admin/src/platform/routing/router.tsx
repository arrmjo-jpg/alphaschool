import { lazy, Suspense } from 'react'
import { createRootRoute, createRoute, createRouter, Navigate, Outlet, redirect } from '@tanstack/react-router'
import { AppShell } from '@/platform/shell/app-shell'
import { HomePage } from '@/platform/shell/home-page'
import { LoginPage } from '@/platform/shell/login-page'
import { WorkspaceBootstrap } from '@/platform/shell/workspace-bootstrap'
import { WorkspaceRoutePage } from '@/platform/shell/workspace-route-page'
import { useAuthStore } from '@/platform/auth/auth-store'
import { ModalHost } from '@/platform/modals/modal-host'
import { CommandPalette } from '@/platform/command-palette/command-palette'
import { ToastHost } from '@/platform/toasts/toast-host'

/**
 * Modal and command-palette hosts live at the true root, not inside
 * AppShell -- they must be available on every route (login, the dev
 * harness) not only behind the auth-gated shell.
 */
const rootRoute = createRootRoute({
  component: () => (
    <>
      <Outlet />
      <ModalHost />
      <CommandPalette />
      <ToastHost />
    </>
  ),
})

const loginRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '/login',
  component: LoginPage,
})

/**
 * A pathless layout route -- the auth guard and AppShell live here
 * exactly once. Every future workspace route nests under this without
 * re-implementing authentication (ADR-0015 Decision 4).
 */
const protectedLayoutRoute = createRoute({
  getParentRoute: () => rootRoute,
  id: 'protected',
  beforeLoad: () => {
    if (useAuthStore.getState().token === null) {
      throw redirect({ to: '/login' })
    }
  },
  component: () => (
    <WorkspaceBootstrap>
      <AppShell />
    </WorkspaceBootstrap>
  ),
})

const indexRoute = createRoute({
  getParentRoute: () => protectedLayoutRoute,
  path: '/',
  component: HomePage,
})

/**
 * A pathless-in-effect parent (its own path is the workspace root) so
 * the index and splat children below are relative siblings ('/' vs
 * '$'), never two routes that can stringify to the identical literal
 * path -- the flat sibling-route version of this tried first produced
 * a real, repeated TanStack Router console warning ("Generated path
 * .../$workspaceKey matched .../$workspaceKey/$ instead"), confirmed
 * live, not a hypothetical risk. This nested shape is TanStack
 * Router's own documented pattern for "index + catch-the-rest" and
 * has no such ambiguity: '/' and '$' cannot both match the same
 * generated string the way two full sibling paths could.
 */
const workspaceRoute = createRoute({
  getParentRoute: () => protectedLayoutRoute,
  path: '/w/$workspaceKey',
  component: () => <Outlet />,
})

const workspaceIndexRoute = createRoute({
  getParentRoute: () => workspaceRoute,
  path: '/',
  component: () => {
    const { workspaceKey } = workspaceIndexRoute.useParams()
    return <WorkspaceRoutePage workspaceKey={workspaceKey} />
  },
})

/**
 * Captures everything past the workspace root (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.2's routing decision -- entity-CRUD workspaces need real,
 * deep-linkable List/Detail/Form state, matching §7.1's own
 * already-frozen "page state lives in the URL" praise, which
 * Configuration Platform/Provider Registry's own local-useState
 * navigation never actually implemented). A workspace that never
 * reads useWorkspaceSubPath is entirely unaffected by this route
 * existing.
 */
const workspaceSplatRoute = createRoute({
  getParentRoute: () => workspaceRoute,
  path: '$',
  component: () => {
    const { workspaceKey, _splat } = workspaceSplatRoute.useParams()
    return <WorkspaceRoutePage workspaceKey={workspaceKey} subPath={_splat ?? ''} />
  },
})

const catchAllRoute = createRoute({
  getParentRoute: () => rootRoute,
  path: '*',
  component: () => <Navigate to="/" />,
})

/**
 * Dev-only proving ground (ADR-0015 Decision 3) -- import.meta.env.DEV
 * is statically replaced with `false` in production builds, so Rollup
 * dead-code-eliminates this entire branch (including the dynamic
 * import of dev-harness.tsx) out of the production bundle rather than
 * merely hiding it behind a runtime check.
 */
const devRoutes = import.meta.env.DEV
  ? [
      createRoute({
        getParentRoute: () => rootRoute,
        path: '/dev/harness',
        component: () => {
          const LazyDevHarness = lazy(() => import('@/dev/dev-harness'))
          return (
            <Suspense fallback={null}>
              <LazyDevHarness />
            </Suspense>
          )
        },
      }),
    ]
  : []

const routeTree = rootRoute.addChildren([
  loginRoute,
  protectedLayoutRoute.addChildren([indexRoute, workspaceRoute.addChildren([workspaceIndexRoute, workspaceSplatRoute])]),
  ...devRoutes,
  catchAllRoute,
])

export const router = createRouter({ routeTree })

declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}
