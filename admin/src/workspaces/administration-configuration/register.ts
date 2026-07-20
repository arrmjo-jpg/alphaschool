import { Settings, SlidersHorizontal } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
import { setConfigurationDataProvider } from '@/platform/administration/configuration-provider'
import { realConfigurationDataProvider } from '@/workspaces/administration-configuration/real-configuration-provider'
import en from '@/workspaces/administration-configuration/locales/en.json'
import ar from '@/workspaces/administration-configuration/locales/ar.json'

/**
 * The Administration group (docs/ADMIN_DESIGN_SYSTEM.md §8.3) and its
 * first registered child, Configuration Platform (§26.1) -- the other
 * eight children stay unregistered until their own phases, per §26.12's
 * Registration Principle: the Administration shell must never assume
 * any specific child is present.
 *
 * The `key` is the fixed architectural capability name; `labelKey`
 * resolves through this workspace's own translation namespace so the
 * end-user-facing label (§26.2) can evolve independently -- eager,
 * synchronous registration, not deferred to the lazy-loaded page
 * component, since SideNav needs the label before the workspace is
 * ever opened.
 */
export function registerConfigurationPlatformWorkspace(): void {
  registerWorkspaceTranslations('administration-configuration', 'en', en)
  registerWorkspaceTranslations('administration-configuration', 'ar', ar)

  // Phase E-B (docs/ADMIN_DESIGN_SYSTEM.md §26.13) -- the real adapter
  // API now exists, so this workspace registers a real provider
  // permanently, not a temporary verification fixture. §26.7's "not
  // connected" state remains correct behavior for any deployment where
  // this call never runs (e.g. a build that hasn't registered the
  // workspace at all), it just no longer describes this one.
  setConfigurationDataProvider(realConfigurationDataProvider)

  registerWorkspace({
    key: 'configuration-platform',
    labelKey: 'administration-configuration:workspace.label',
    icon: SlidersHorizontal,
    requiredPermission: 'identity.view-otp-settings',
    navItems: [],
    group: {
      key: 'administration',
      labelKey: 'administration-configuration:workspace.group.label',
      icon: Settings,
    },
    loadComponent: () => import('@/workspaces/administration-configuration/configuration-platform-page'),
  })
}
