import type { WidgetDefinition } from '@/platform/widgets/widget-definition'

export type KpiData = { value: string | number; trend?: 'up' | 'down' | 'flat' }

/**
 * The one generic widget implementation this milestone ships (ADR-0015
 * execution plan) -- number + label + trend arrow, proven against a
 * mock/dev data source since no real Reporting endpoint exists yet.
 */
export function createKpiWidget(options: {
  id: string
  titleKey: string
  requiredPermission?: string
  dataSource: () => Promise<KpiData>
}): WidgetDefinition<KpiData> {
  return {
    id: options.id,
    titleKey: options.titleKey,
    requiredPermission: options.requiredPermission ?? null,
    dataSource: options.dataSource,
    render: (data) => (
      <div className="flex items-baseline gap-2">
        <span className="text-2xl font-semibold text-foreground">{data.value}</span>
        {data.trend && (
          <span
            className={
              data.trend === 'up' ? 'text-xs text-green-600' : data.trend === 'down' ? 'text-xs text-red-600' : 'text-xs text-muted-foreground'
            }
          >
            {data.trend === 'up' ? '▲' : data.trend === 'down' ? '▼' : '▬'}
          </span>
        )}
      </div>
    ),
  }
}
