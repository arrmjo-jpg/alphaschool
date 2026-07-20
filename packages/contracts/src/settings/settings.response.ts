import { z } from 'zod'
import { SettingCategorySchema, SettingFieldValueSchema } from './settings.schemas'

export const ListSettingCategoriesResponseSchema = z.object({
  categories: z.array(SettingCategorySchema),
})

export const ListCategorySettingsResponseSchema = z.object({
  settings: z.array(SettingFieldValueSchema),
})

/**
 * `status` distinguishes an immediately-active write from one
 * `SettingsResolver::write()` routed through the Approval Engine
 * instead (`ConfigurationValue::STATUS_PENDING_APPROVAL`) -- a caller
 * must never assume "the request succeeded" means "the value is live."
 */
export const WriteSettingResponseSchema = z.object({
  key: z.string(),
  value: z.unknown(),
  version: z.number().int(),
  status: z.enum(['active', 'pending_approval']),
})

export type ListSettingCategoriesResponse = z.infer<typeof ListSettingCategoriesResponseSchema>
export type ListCategorySettingsResponse = z.infer<typeof ListCategorySettingsResponseSchema>
export type WriteSettingResponse = z.infer<typeof WriteSettingResponseSchema>
