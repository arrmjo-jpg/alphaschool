import { z } from 'zod'

/**
 * `expectedVersion` is mandatory, not optional -- `SettingsResolver::write()`
 * has no "just overwrite" path (ADR-0018 Decision 8's optimistic-locking
 * contract), so the request schema doesn't offer one either.
 */
export const WriteSettingRequestSchema = z.object({
  value: z.unknown(),
  expectedVersion: z.number().int(),
})

export type WriteSettingRequest = z.infer<typeof WriteSettingRequestSchema>
