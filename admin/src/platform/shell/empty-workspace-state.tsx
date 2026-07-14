import { Inbox } from 'lucide-react'
import { useTranslation } from 'react-i18next'

/**
 * The Admin Platform Foundation's primary acceptance state (ADR-0015
 * Decision 5) -- rendered when zero workspaces are registered/visible,
 * which is the correct state for this milestone, not an edge case.
 */
export function EmptyWorkspaceState() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-3 p-12 text-center">
      <Inbox className="size-10 text-muted-foreground" />
      <h2 className="text-base font-medium">{t('shell.emptyWorkspaces.title')}</h2>
      <p className="max-w-sm text-sm text-muted-foreground">{t('shell.emptyWorkspaces.body')}</p>
    </div>
  )
}
