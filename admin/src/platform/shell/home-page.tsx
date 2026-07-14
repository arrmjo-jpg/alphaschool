import { Link } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { EmptyWorkspaceState } from '@/platform/shell/empty-workspace-state'

/** The shell's landing page -- a workspace launcher, never business content itself. */
export function HomePage() {
  const { t } = useTranslation()
  const { workspaces, isLoading } = useVisibleWorkspaces()

  if (isLoading) return null
  if (workspaces.length === 0) return <EmptyWorkspaceState />

  return (
    <div className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
      {workspaces.map((workspace) => {
        const Icon = workspace.icon
        return (
          <Link
            key={workspace.key}
            to="/w/$workspaceKey"
            params={{ workspaceKey: workspace.key }}
            className="flex flex-col items-center gap-2 rounded-lg border bg-card p-6 text-card-foreground hover:bg-accent"
          >
            <Icon className="size-6" />
            <span className="text-sm font-medium">{t(workspace.labelKey, workspace.labelKey)}</span>
          </Link>
        )
      })}
    </div>
  )
}
