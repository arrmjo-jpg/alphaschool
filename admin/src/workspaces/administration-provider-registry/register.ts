import { Settings, Cable } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
import { setProviderRegistryDataProvider } from '@/platform/administration/provider-registry-provider'
import { realProviderRegistryDataProvider } from '@/workspaces/administration-provider-registry/real-provider-registry-provider'
import en from '@/workspaces/administration-provider-registry/locales/en.json'
import ar from '@/workspaces/administration-provider-registry/locales/ar.json'

/**
 * Provider Registry, the second implemented child of the Administration
 * group (docs/ADMIN_DESIGN_SYSTEM.md §27.12's Registration Principle,
 * unchanged from §26.12).
 *
 * Phase F-B (§27.13): the real adapter API now exists, so this
 * workspace registers a real provider permanently, not a temporary
 * verification fixture -- mirrors Configuration Platform's own E-A/E-B
 * split exactly. §27.7's "not connected" state (slots.notConnected.*)
 * remains correct behavior for any deployment where this call never
 * runs; it just no longer describes this one.
 *
 * `requiredPermission` is advisory-only (see WorkspaceDefinition's own
 * doc comment: "real visibility is server-computed") -- the real
 * enforcement is administration.providers.view, checked server-side by
 * WorkspaceAccessResolver (§27.6, resolved by explicit confirmation: a
 * dedicated workspace-visibility permission, never inferred from the
 * union of per-slot edit permissions).
 */
export function registerProviderRegistryWorkspace(): void {
  registerWorkspaceTranslations('administration-provider-registry', 'en', en)
  registerWorkspaceTranslations('administration-provider-registry', 'ar', ar)

  setProviderRegistryDataProvider(realProviderRegistryDataProvider)

  registerWorkspace({
    key: 'provider-registry',
    labelKey: 'administration-provider-registry:workspace.label',
    icon: Cable,
    requiredPermission: 'administration.providers.view',
    navItems: [],
    group: {
      key: 'administration',
      labelKey: 'administration-provider-registry:workspace.group.label',
      icon: Settings,
    },
    loadComponent: () => import('@/workspaces/administration-provider-registry/provider-registry-page'),
  })
}
