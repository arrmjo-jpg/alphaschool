import { Calendar } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
import en from '@/workspaces/academic/locales/en.json'
import ar from '@/workspaces/academic/locales/ar.json'

/**
 * The Academic workspace (docs/ADMIN_DESIGN_SYSTEM.md §28.17, UI
 * Sprint 1-B) -- flat top-level, per §8.3's proposed structure, not
 * grouped under Administration. `requiredPermission` is advisory only
 * (WorkspaceDefinition's own doc comment: "real visibility is
 * server-computed") -- the real gate is `academic.manage-catalog`,
 * checked server-side by WorkspaceAccessResolver, reusing the one
 * permission Sprint 4.1 already seeded for catalog management rather
 * than inventing a workspace-only one.
 */
export function registerAcademicWorkspace(): void {
  registerWorkspaceTranslations('academic', 'en', en)
  registerWorkspaceTranslations('academic', 'ar', ar)

  registerWorkspace({
    key: 'academic',
    labelKey: 'academic:workspace.label',
    icon: Calendar,
    requiredPermission: 'academic.manage-catalog',
    navItems: [],
    loadComponent: () => import('@/workspaces/academic/academic-workspace-page'),
  })
}
