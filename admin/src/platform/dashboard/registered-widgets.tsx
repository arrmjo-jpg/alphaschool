import { Dashboard } from '@/platform/dashboard/dashboard'
import { getDashboardWidgets } from '@/platform/dashboard/dashboard-widget-registry'

/**
 * Renders nothing at zero -- same convention as QuickActions (§25.2):
 * an always-empty-until-something-exists section shouldn't apologize
 * for itself between now and whenever the first real widget ships.
 */
export function RegisteredWidgets() {
  const widgets = getDashboardWidgets()

  if (widgets.length === 0) return null

  return <Dashboard widgets={widgets} />
}
