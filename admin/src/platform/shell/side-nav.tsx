import { useTranslation } from 'react-i18next'
import { Link, useRouterState } from '@tanstack/react-router'
import { PanelLeftClose, PanelLeftOpen } from 'lucide-react'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { Button } from '@/platform/components/ui/button'
import { cn } from '@/lib/cn'

/**
 * Renders exactly what use-visible-workspaces returns -- the
 * intersection of the local registry and the server's permission-
 * computed workspace-access response. Correctly renders nothing when
 * that list is empty (the primary acceptance state, see
 * EmptyWorkspaceState), never a placeholder item.
 */
export function SideNav({ collapsed, onToggle }: { collapsed: boolean; onToggle: () => void }) {
  const { t } = useTranslation()
  const { workspaces } = useVisibleWorkspaces()
  const pathname = useRouterState({ select: (state) => state.location.pathname })

  return (
    <nav
      className={cn(
        'flex h-full flex-col gap-1 border-e bg-card p-2 transition-[width]',
        collapsed ? 'w-14' : 'w-56',
      )}
    >
      <div className="flex-1 space-y-1">
        {workspaces.map((workspace) => {
          const Icon = workspace.icon
          const active = pathname.startsWith(`/w/${workspace.key}`)
          return (
            <Link
              key={workspace.key}
              to="/w/$workspaceKey"
              params={{ workspaceKey: workspace.key }}
              className={cn(
                'flex items-center gap-2 rounded-md px-2 py-2 text-sm transition-colors hover:bg-accent',
                active && 'bg-accent text-accent-foreground',
              )}
            >
              <Icon className="size-4 shrink-0" />
              {!collapsed && <span>{t(workspace.labelKey, workspace.labelKey)}</span>}
            </Link>
          )
        })}
      </div>
      <Button variant="ghost" size="icon" onClick={onToggle} aria-label={t(collapsed ? 'shell.nav.expand' : 'shell.nav.collapse')}>
        {collapsed ? <PanelLeftOpen className="size-4" /> : <PanelLeftClose className="size-4" />}
      </Button>
    </nav>
  )
}
