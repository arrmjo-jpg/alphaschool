import type { LucideIcon } from 'lucide-react'

/**
 * The Configuration Platform data contract (docs/ADMIN_DESIGN_SYSTEM.md
 * §26.7/§26.13) -- mirrors SearchProviderDefinition's "zero registered
 * = honestly not connected" pattern exactly, not NotificationProvider's
 * always-empty-mock pattern. The difference is deliberate: a
 * Notification Engine returning zero notifications is a normal,
 * expected state; a Configuration Platform with zero providers means
 * the real backend integration (Phase E-B, a thin adapter over the
 * already-complete SettingsResolver/ConfigurationRegistry) has not
 * shipped yet, which is a "not connected" state, not a "nothing to
 * show" one -- §26.7 is explicit that this workspace's whole purpose is
 * showing real resolved values, so it must never quietly render as if
 * empty were the same thing as disconnected.
 *
 * `resolvedFrom` intentionally has no live Branch-context wiring yet --
 * Global Context (§24) is itself frozen design, not yet implemented in
 * code, so there is nothing real to wire against. Once it exists,
 * `fetchCategorySettings`'s branch scoping becomes additive here, not a
 * redesign.
 */
export type SettingFieldDataType = 'text' | 'number' | 'boolean' | 'select'

export type SettingFieldOption = {
  value: string
  labelKey: string
}

export type SettingFieldValue = {
  key: string
  labelKey: string
  dataType: SettingFieldDataType
  options?: SettingFieldOption[]
  value: unknown
  /** Which altitude this value is currently resolving from (§26.4). */
  resolvedFrom: 'global' | 'branch' | 'user'
  /** No separate `canView` flag -- a field the caller cannot view is never included in the response at all, the same server-computed-access convention `useWorkspaceAccess`/`useVisibleWorkspaces` already use. */
  canEdit: boolean
  approvalRequired: boolean
}

export type SettingCategoryStatus = 'ready' | 'needs-setup' | 'error' | 'disabled'

export type SettingCategory = {
  key: string
  labelKey: string
  icon: LucideIcon
  status: SettingCategoryStatus
  /** Optional short line, e.g. the active provider name -- never a chart, count, or stat. */
  secondaryLine?: string
}

export type ConfigurationDataProvider = {
  /** Expected to already be permission-filtered -- only categories containing at least one field the current user can view (§26.6). */
  fetchCategories: () => Promise<SettingCategory[]>
  /** Expected to already be permission-filtered -- only fields the current user can view (§26.9's disabled-with-note state is for canEdit === false, never for something hidden entirely). */
  fetchCategorySettings: (categoryKey: string) => Promise<SettingFieldValue[]>
  writeSetting: (categoryKey: string, fieldKey: string, value: unknown) => Promise<void>
}

let activeProvider: ConfigurationDataProvider | null = null

export function setConfigurationDataProvider(provider: ConfigurationDataProvider): void {
  activeProvider = provider
}

export function getConfigurationDataProvider(): ConfigurationDataProvider | null {
  return activeProvider
}
