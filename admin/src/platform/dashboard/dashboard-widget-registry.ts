import type { WidgetDefinition } from '@/platform/widgets/widget-definition'

/**
 * The Dashboard's widget registration model (docs/ADMIN_DESIGN_SYSTEM.md
 * §25.1/§25.3) -- mirrors the Workspace registry's own pattern exactly:
 * a future module contributes a widget with one call here, nothing else
 * in `src/platform` changes. Reuses `WidgetDefinition` verbatim (no new
 * type) -- a global Dashboard widget and a per-workspace one are the
 * same shape, and `WidgetHost`/`Dashboard` already render either
 * correctly. Zero widgets registered today is correct, not a gap to
 * fill before shipping (§25.3: this phase owns presentation and
 * composition only, never a specific widget).
 */
const widgets: WidgetDefinition<any>[] = []

export function registerDashboardWidget(widget: WidgetDefinition<any>): void {
  widgets.push(widget)
}

export function getDashboardWidgets(): WidgetDefinition<any>[] {
  return widgets
}
