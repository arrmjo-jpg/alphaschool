import { Settings, Cable } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
import en from '@/workspaces/administration-provider-registry/locales/en.json'
import ar from '@/workspaces/administration-provider-registry/locales/ar.json'

/**
 * Provider Registry, the second implemented child of the Administration
 * group (docs/ADMIN_DESIGN_SYSTEM.md §27.12's Registration Principle,
 * unchanged from §26.12) -- Phase F-A registers the workspace shell and
 * its translations; it deliberately does NOT call
 * setProviderRegistryDataProvider() yet, since no real backend adapter
 * exists (that's Phase F-B, mirroring Configuration Platform's own
 * E-A/E-B split exactly). The "not connected" state (slots.notConnected.*)
 * is genuinely correct behavior for this phase, not a placeholder.
 *
 * `requiredPermission` is advisory-only (see WorkspaceDefinition's own
 * doc comment: "real visibility is server-computed") -- §27.6 resolved
 * that visibility is gated by general Administration access, not a
 * per-slot permission, but the concrete backend permission string (or
 * bypass rule) that realizes that is an explicit Phase F-B decision, not
 * decided here. This value is a placeholder documenting intent only.
 */
export function registerProviderRegistryWorkspace(): void {
  registerWorkspaceTranslations('administration-provider-registry', 'en', en)
  registerWorkspaceTranslations('administration-provider-registry', 'ar', ar)

  registerWorkspace({
    key: 'provider-registry',
    labelKey: 'administration-provider-registry:workspace.label',
    icon: Cable,
    requiredPermission: 'administration.access',
    navItems: [],
    group: {
      key: 'administration',
      labelKey: 'administration-provider-registry:workspace.group.label',
      icon: Settings,
    },
    loadComponent: () => import('@/workspaces/administration-provider-registry/provider-registry-page'),
  })
}
