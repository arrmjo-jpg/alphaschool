import { WidgetHost } from '@/platform/widgets/widget-host'
import type { WidgetDefinition } from '@/platform/widgets/widget-definition'

/**
 * A workspace declares its dashboard as an ordered WidgetDefinition
 * list; this renders the responsive grid. Per-user layout
 * drag/resize is deferred (no generic preferences store exists yet --
 * Administration Platform, ADR-0011 -- so persisting a custom order
 * would need a bespoke backend call this milestone isn't scoped to
 * add); the grid order is simply the array order today, swappable
 * later with no change to how a workspace declares its widgets.
 */
export function Dashboard({ widgets }: { widgets: WidgetDefinition<any>[] }) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      {widgets.map((widget) => (
        <WidgetHost key={widget.id} widget={widget} />
      ))}
    </div>
  )
}
