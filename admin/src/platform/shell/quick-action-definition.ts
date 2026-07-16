import type { LucideIcon } from 'lucide-react'

/**
 * The Quick Actions registry (docs/ADMIN_DESIGN_SYSTEM.md's Dashboard
 * Philosophy, adapted for shell-level surfacing) -- mirrors
 * WidgetDefinition/SearchProviderDefinition's shape deliberately: a
 * workspace declares an action, the shell decides where/whether to
 * render it, permission-filtered the same way SideNav's own workspace
 * list already is. Zero actions are registered today (Phase B builds
 * no business pages) -- QuickActions renders nothing until a real
 * workspace registers one, the same "correct with zero" acceptance bar
 * already proven for the Workspace registry itself.
 */
export type QuickActionDefinition = {
  id: string
  labelKey: string
  icon: LucideIcon
  requiredPermission: string | null
  run: () => void
}

const actions: QuickActionDefinition[] = []

export function registerQuickAction(action: QuickActionDefinition): void {
  actions.push(action)
}

export function getQuickActions(): QuickActionDefinition[] {
  return actions
}
