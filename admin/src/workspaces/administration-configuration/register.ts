import { Settings, SlidersHorizontal } from 'lucide-react'
import { registerWorkspace } from '@/workspaces/registry'
import { registerWorkspaceTranslations } from '@/platform/i18n'
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
