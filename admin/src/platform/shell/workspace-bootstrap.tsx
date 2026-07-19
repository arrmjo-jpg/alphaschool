import { type ReactNode } from 'react'
import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useMe } from '@/platform/auth/use-me'
import { useWorkspaceAccess } from '@/platform/auth/use-workspaces'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The "Workspace Bootstrap" step (docs/ADMIN_DESIGN_SYSTEM.md §20.9,
 * Loading Experience -> Workspace Bootstrap -> Dashboard). Previously
 * `/me` and `/workspaces` resolved silently inside whichever component
 * happened to call `useMe()`/`useVisibleWorkspaces()` first, with no
 * coherent transition -- this gates the entire protected layout behind
 * both resolving, so the jump from a successful login into the
 * Dashboard is one deliberate, branded step instead of an abrupt or
 * partially-hydrated shell. staleTime on both queries (60s) means this
 * only shows once per session in practice, immediately after login,
 * not on every route change.
 */
export function WorkspaceBootstrap({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { isLoading: meLoading } = useMe()
  const { isLoading: workspacesLoading } = useWorkspaceAccess()

  if (meLoading || workspacesLoading) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3" aria-live="polite" aria-busy="true">
        <Loader2 className={cn(ICON_SIZE.prominent, 'animate-spin text-primary')} aria-hidden="true" />
        <p className="text-sm text-muted-foreground">{t('shell.bootstrap.loading')}</p>
      </div>
    )
  }

  return <>{children}</>
}
