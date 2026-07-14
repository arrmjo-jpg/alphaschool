import type { ReactNode } from 'react'

/**
 * A KPI widget declares which endpoint to call, never its own
 * calculation logic (docs/ADMIN_PLATFORM.md's dashboard-widget registry
 * row) -- the real report/aggregation logic belongs to a future
 * Reporting backend this milestone deliberately does not build
 * (ADR-0015 Decision 6).
 */
export type WidgetDefinition<T = unknown> = {
  id: string
  titleKey: string
  requiredPermission: string | null
  dataSource: () => Promise<T>
  render: (data: T) => ReactNode
}
