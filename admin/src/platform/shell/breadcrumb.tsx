import { useTranslation } from 'react-i18next'
import { Link, useRouterState } from '@tanstack/react-router'
import { ChevronRight, LayoutDashboard } from 'lucide-react'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { useBreadcrumbStore } from '@/platform/shell/breadcrumb-store'
import { cn } from '@/lib/cn'

/**
 * A genuine multi-level trail (docs/ADMIN_DESIGN_SYSTEM.md §11 --
 * explicitly NOT a port of the legacy admin's own breadcrumb, which
 * only ever showed two levels regardless of how deep a page actually
 * was). Home -> current workspace -> any segments that workspace's own
 * page contributed via useBreadcrumbSegments. Renders nothing on the
 * home route itself -- a trail with one, un-clickable entry pointing
 * at the page already being viewed has no value.
 */
export function Breadcrumb() {
  const { t } = useTranslation()
  const pathname = useRouterState({ select: (state) => state.location.pathname })
  const { workspaces } = useVisibleWorkspaces()
  const extraSegments = useBreadcrumbStore((state) => state.segments)

  if (pathname === '/') return null

  const workspace = workspaces.find(
    (candidate) => pathname === `/w/${candidate.key}` || pathname.startsWith(`/w/${candidate.key}/`),
  )

  if (!workspace) return null

  const WorkspaceIcon = workspace.icon
  const isWorkspaceRootCurrent = extraSegments.length === 0

  return (
    <nav aria-label={t('shell.breadcrumb.label', 'Breadcrumb')} className="flex min-w-0 items-center gap-1.5 text-sm">
      <Link
        to="/"
        className="flex shrink-0 items-center gap-1.5 rounded-md text-muted-foreground outline-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={t('shell.breadcrumb.home', 'Dashboard')}
      >
        <LayoutDashboard className="size-4" />
      </Link>

      <ChevronRight className="size-3.5 shrink-0 text-muted-foreground rtl:rotate-180" />

      {isWorkspaceRootCurrent ? (
        <span className="flex min-w-0 items-center gap-1.5 font-medium text-foreground">
          <WorkspaceIcon className="size-4 shrink-0" />
          <span className="truncate">{t(workspace.labelKey, workspace.labelKey)}</span>
        </span>
      ) : (
        <Link
          to="/w/$workspaceKey"
          params={{ workspaceKey: workspace.key }}
          className="flex min-w-0 items-center gap-1.5 rounded-md text-muted-foreground outline-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
        >
          <WorkspaceIcon className="size-4 shrink-0" />
          <span className="truncate">{t(workspace.labelKey, workspace.labelKey)}</span>
        </Link>
      )}

      {extraSegments.map((segment, index) => {
        const isLast = index === extraSegments.length - 1
        return (
          <span key={`${segment.label}-${index}`} className="flex min-w-0 items-center gap-1.5">
            <ChevronRight className="size-3.5 shrink-0 text-muted-foreground rtl:rotate-180" />
            {segment.href && !isLast ? (
              <Link
                to={segment.href}
                className={cn(
                  'truncate rounded-md text-muted-foreground outline-none transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring',
                )}
              >
                {segment.label}
              </Link>
            ) : (
              <span className="truncate font-medium text-foreground">{segment.label}</span>
            )}
          </span>
        )
      })}
    </nav>
  )
}
