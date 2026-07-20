import { z } from 'zod'

/**
 * The Configuration Platform's wire contract (Phase E-B,
 * docs/ADMIN_DESIGN_SYSTEM.md §26.13/§26.16). Deliberately excludes
 * anything display-only (icon, translated label) -- the backend's
 * `ConfigurationDefinition` model (backend/app/Modules/Administration/
 * Models/ConfigurationDefinition.php) has no label/icon field at all,
 * by design; category and field `key` are the only identifiers the
 * wire contract carries, and the consuming frontend maps a known key
 * to a translation/icon client-side, falling back to the raw key if
 * unrecognized -- the same `t(key, key)` fallback convention already
 * used everywhere else in admin/.
 *
 * `key` for a category is a Capability slug (docs/ADMINISTRATION_PLATFORM.md
 * §2's ten-capability taxonomy, e.g. "access-governance"), not an
 * invented grouping -- Configuration Platform's own real data already
 * groups by `capability`, so the Overview Grid's "configuration area"
 * concept is literally that taxonomy, not a parallel one.
 */
export const SettingCategoryStatusSchema = z.enum(['ready', 'needs-setup', 'error', 'disabled'])

export const SettingCategorySchema = z.object({
  key: z.string(),
  status: SettingCategoryStatusSchema,
  secondaryLine: z.string().optional(),
})

export const SettingFieldDataTypeSchema = z.enum(['text', 'number', 'boolean', 'select'])

export const SettingFieldOptionSchema = z.object({
  value: z.string(),
  labelKey: z.string(),
})

/**
 * Matches `ResolvedSetting::$resolvedAtAltitude` exactly (backend/app/Core/
 * ValueObjects/ResolvedSetting.php): `null` (no ConfigurationValue row at
 * any eligible altitude, using the declared default) maps to `'default'`;
 * a real row at Global or Branch altitude maps to the matching literal.
 * `SettingsResolver` has no third "user" altitude -- `ConfigurationScopeContext`
 * itself documents that User Preferences are "deliberately not represented
 * here at all... a separate, parallel, lower-ceremony mechanism" -- so this
 * schema does not invent one either.
 */
export const SettingResolvedFromSchema = z.enum(['default', 'global', 'branch'])

export const SettingFieldValueSchema = z.object({
  key: z.string(),
  dataType: SettingFieldDataTypeSchema,
  options: z.array(SettingFieldOptionSchema).optional(),
  value: z.unknown(),
  resolvedFrom: SettingResolvedFromSchema,
  canEdit: z.boolean(),
  approvalRequired: z.boolean(),
  /** The optimistic-locking token `SettingsResolver::write()`'s `$expectedVersion` requires back (ADR-0018 Decision 8). */
  version: z.number().int(),
})

export type SettingCategoryStatus = z.infer<typeof SettingCategoryStatusSchema>
export type SettingCategory = z.infer<typeof SettingCategorySchema>
export type SettingFieldDataType = z.infer<typeof SettingFieldDataTypeSchema>
export type SettingFieldOption = z.infer<typeof SettingFieldOptionSchema>
export type SettingResolvedFrom = z.infer<typeof SettingResolvedFromSchema>
export type SettingFieldValue = z.infer<typeof SettingFieldValueSchema>
