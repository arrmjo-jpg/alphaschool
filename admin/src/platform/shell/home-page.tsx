import { Link } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { EmptyWorkspaceState } from '@/platform/shell/empty-workspace-state'
import { SystemInitializationState } from '@/platform/shell/system-initialization-state'
import { QuickActions } from '@/platform/shell/quick-actions'
import { RegisteredWidgets } from '@/platform/dashboard/registered-widgets'
import { NotificationsSummary } from '@/platform/notifications/notifications-summary'
import { getRegisteredWorkspaces } from '@/workspaces/registry'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * The Dashboard shell (docs/ADMIN_DESIGN_SYSTEM.md §25) -- HomePage
 * extended, not replaced by a second landing page. Two genuinely
 * different empty conditions are distinguished by signal, not by a
 * single generic "nothing here" state (§25.2): the local, static
 * workspace registry being empty means no module has been *built into
 * this deployment* at all (System Initialization, a deployment fact
 * true for everyone), while the registry being non-empty but the
 * server-filtered visible list being empty means this specific user
 * isn't licensed for any of them (an ordinary permission gap,
 * EmptyWorkspaceState's existing copy is correct there).
 */
export function HomePage() {
  const { t } = useTranslation()
  const { workspaces, isLoading } = useVisibleWorkspaces()

  if (getRegisteredWorkspaces().length === 0) return <SystemInitializationState />
  if (isLoading) return null
  if (workspaces.length === 0) return <EmptyWorkspaceState />

  return (
    <div className="flex flex-col gap-6">
      <QuickActions />
      <RegisteredWidgets />
      <NotificationsSummary />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {workspaces.map((workspace) => {
          const Icon = workspace.icon
          return (
            <Link
              key={workspace.key}
              to="/w/$workspaceKey"
              params={{ workspaceKey: workspace.key }}
              className="flex flex-col items-center gap-2 rounded-md border bg-card p-6 text-card-foreground hover:bg-accent"
            >
              <Icon className={ICON_SIZE.prominent} />
              <span className="text-sm font-medium">{t(workspace.labelKey, workspace.labelKey)}</span>
            </Link>
          )
        })}
      </div>
    </div>
  )
}
