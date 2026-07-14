import { lazy, Suspense } from 'react'
import { createRootRoute, createRoute, createRouter, Navigate, Outlet, redirect } from '@tanstack/react-router'
import { AppShell } from '@/platform/shell/app-shell'
import { HomePage } from '@/platform/shell/home-page'
import { LoginPage } from '@/platform/shell/login-page'
import { WorkspaceRoutePage } from '@/platform/shell/workspace-route-page'
import { useAuthStore } from '@/platform/auth/auth-store'
import { ModalHost } from '@/platform/modals/modal-host'
import { CommandPalette } from '@/platform/command-palette/command-palette'

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
  component: AppShell,
})

const indexRoute = createRoute({
  getParentRoute: () => protectedLayoutRoute,
  path: '/',
  component: HomePage,
})

const workspaceRoute = createRoute({
  getParentRoute: () => protectedLayoutRoute,
  path: '/w/$workspaceKey',
  component: () => {
    const { workspaceKey } = workspaceRoute.useParams()
    return <WorkspaceRoutePage workspaceKey={workspaceKey} />
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
  protectedLayoutRoute.addChildren([indexRoute, workspaceRoute]),
  ...devRoutes,
  catchAllRoute,
])

export const router = createRouter({ routeTree })

declare module '@tanstack/react-router' {
  interface Register {
    router: typeof router
  }
}
