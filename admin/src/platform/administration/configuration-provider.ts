import type { LucideIcon } from 'lucide-react'
import type {
  SettingCategory as WireSettingCategory,
  SettingFieldValue as WireSettingFieldValue,
  SettingFieldDataType,
  SettingCategoryStatus,
  SettingResolvedFrom,
  WriteSettingResponse,
} from '@alphaschool/contracts'

/**
 * The Configuration Platform data contract (docs/ADMIN_DESIGN_SYSTEM.md
 * §26.7/§26.13/§26.16) -- mirrors SearchProviderDefinition's "zero
 * registered = honestly not connected" pattern exactly, not
 * NotificationProvider's always-empty-mock pattern. The difference is
 * deliberate: a Notification Engine returning zero notifications is a
 * normal, expected state; a Configuration Platform with zero providers
 * means the real backend integration (Phase E-B) hasn't shipped, which
 * is a "not connected" state, not a "nothing to show" one.
 *
 * The wire shapes (`SettingCategory`/`SettingFieldValue` from
 * `@alphaschool/contracts`, Phase E-B, per docs/adr/0023-zod-first-api-contracts.md
 * Decision 1) are extended here with `icon`/`labelKey` -- display-only
 * concerns the backend's `ConfigurationDefinition` model deliberately
 * does not own (it has no label/icon field at all). A provider
 * implementation is responsible for this enrichment, not the
 * components consuming it -- see administration-configuration's real
 * provider for the actual key -> icon/labelKey mapping.
 */
export type { SettingFieldDataType, SettingCategoryStatus, SettingResolvedFrom }

export type SettingFieldOption = {
  value: string
  labelKey: string
}

export type SettingFieldValue = Omit<WireSettingFieldValue, 'options'> & {
  labelKey: string
  options?: SettingFieldOption[]
}

export type SettingCategory = WireSettingCategory & {
  labelKey: string
  icon: LucideIcon
}

export type ConfigurationDataProvider = {
  /** Expected to already be permission-filtered -- only categories containing at least one field the current user can view (§26.6). */
  fetchCategories: () => Promise<SettingCategory[]>
  /** Expected to already be permission-filtered -- only fields the current user can view (§26.9's disabled-with-note state is for canEdit === false, never for something hidden entirely). */
  fetchCategorySettings: (categoryKey: string) => Promise<SettingFieldValue[]>
  /**
   * `expectedVersion` is mandatory, mirroring `SettingsResolver::write()`'s
   * own optimistic-locking contract (ADR-0018 Decision 8) -- there is no
   * "just overwrite" path server-side, so this contract doesn't offer
   * one either. Returns the server's authoritative new version so the
   * caller can keep editing without an extra round trip.
   */
  writeSetting: (categoryKey: string, fieldKey: string, value: unknown, expectedVersion: number) => Promise<WriteSettingResponse>
}

let activeProvider: ConfigurationDataProvider | null = null

export function setConfigurationDataProvider(provider: ConfigurationDataProvider): void {
  activeProvider = provider
}

export function getConfigurationDataProvider(): ConfigurationDataProvider | null {
  return activeProvider
}
