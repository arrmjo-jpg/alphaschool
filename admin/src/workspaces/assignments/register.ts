import { CalendarClock } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
import en from '@/workspaces/assignments/locales/en.json'
import ar from '@/workspaces/assignments/locales/ar.json'

/**
 * The Assignments workspace (docs/ADMIN_DESIGN_SYSTEM.md §30.2, UI
 * Sprint 2) -- deliberately its own top-level workspace, not folded
 * into Academic (§29's own closure confirmed Academic is Reference/
 * Master Data specifically; an Assignment Engine with its own lifecycle
 * and timeline is a different concern, per explicit product decision).
 * `requiredPermission` reuses `academic.manage-catalog` -- the same
 * server-side gate HomeroomAssignmentController itself checks, not a
 * new workspace-only permission.
 */
export function registerAssignmentsWorkspace(): void {
  registerWorkspaceTranslations('assignments', 'en', en)
  registerWorkspaceTranslations('assignments', 'ar', ar)

  registerWorkspace({
    key: 'assignments',
    labelKey: 'assignments:workspace.label',
    icon: CalendarClock,
    requiredPermission: 'academic.manage-catalog',
    navItems: [],
    loadComponent: () => import('@/workspaces/assignments/assignments-workspace-page'),
  })
}
