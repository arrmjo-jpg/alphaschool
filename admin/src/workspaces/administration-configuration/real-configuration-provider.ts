import { Shield, SlidersHorizontal, type LucideIcon } from 'lucide-react'
import {
  ListSettingCategoriesResponseSchema,
  ListCategorySettingsResponseSchema,
  WriteSettingResponseSchema,
  WriteSettingRequestSchema,
} from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { ConfigurationDataProvider, SettingCategory, SettingFieldValue } from '@/platform/administration/configuration-provider'

/**
 * The real Phase E-B provider (docs/ADMIN_DESIGN_SYSTEM.md §26.13) --
 * the only thing that changes when swapping from the honest
 * "not connected" state to real data (§26.7): the interface consumed
 * by every page template is unchanged. Every response is validated
 * through `@alphaschool/contracts`' Zod schemas before use (ADR-0023
 * Decision 3) -- the frontend never consumes unvalidated JSON.
 *
 * Icon/labelKey enrichment lives here, not in the wire contract or the
 * consuming components -- the backend's ConfigurationDefinition model
 * deliberately has no display metadata (§26.7), and a provider is
 * exactly the layer responsible for mapping a known capability/field
 * key to a real icon and translation key. An unrecognized key (a
 * capability or field this map hasn't been extended for yet) still
 * renders correctly with a sensible fallback icon and its raw key as
 * the label -- never a crash, matching this codebase's `t(key, key)`
 * fallback convention everywhere else.
 */
const CATEGORY_ICONS: Record<string, LucideIcon> = {
  'access-governance': Shield,
}

const DEFAULT_CATEGORY_ICON: LucideIcon = SlidersHorizontal

export const realConfigurationDataProvider: ConfigurationDataProvider = {
  async fetchCategories(): Promise<SettingCategory[]> {
    const raw = await apiFetch('/administration/configuration/categories')
    const { categories } = ListSettingCategoriesResponseSchema.parse(raw)

    return categories.map((category) => ({
      ...category,
      labelKey: `administration-configuration:category.${category.key}`,
      icon: CATEGORY_ICONS[category.key] ?? DEFAULT_CATEGORY_ICON,
    }))
  },

  async fetchCategorySettings(categoryKey: string): Promise<SettingFieldValue[]> {
    const raw = await apiFetch(`/administration/configuration/categories/${categoryKey}/settings`)
    const { settings } = ListCategorySettingsResponseSchema.parse(raw)

    return settings.map((setting) => ({
      ...setting,
      labelKey: `administration-configuration:field.${setting.key}`,
    }))
  },

  async writeSetting(categoryKey, fieldKey, value, expectedVersion) {
    const body = WriteSettingRequestSchema.parse({ value, expectedVersion })
    const raw = await apiFetch(`/administration/configuration/categories/${categoryKey}/settings/${fieldKey}`, {
      method: 'PATCH',
      body,
    })

    return WriteSettingResponseSchema.parse(raw)
  },
}
