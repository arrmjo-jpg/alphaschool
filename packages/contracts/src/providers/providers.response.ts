import { z } from 'zod'
import { ProviderSlotSchema } from './providers.schemas'

export const ListProviderSlotsResponseSchema = z.object({
  slots: z.array(ProviderSlotSchema),
})

/**
 * Deliberately just `{ ok: boolean }` -- TestsCredentials::testCredentials()
 * (backend/app/Core/Contracts/TestsCredentials.php) returns a plain
 * bool, mirroring HealthCheckable's own conservative, message-less
 * result exactly. A failed test surfaces as a generic, translated
 * failure message client-side, never a backend-supplied diagnostic string.
 */
export const TestProviderCredentialsResponseSchema = z.object({
  ok: z.boolean(),
})

/**
 * `status` distinguishes an immediately-active write from one
 * ProviderCredentialVault::write() routed through the Approval Engine
 * instead (`ProviderCredential::STATUS_PENDING_APPROVAL`) -- mirrors
 * WriteSettingResponseSchema's identical field exactly.
 */
export const WriteProviderCredentialsResponseSchema = z.object({
  version: z.number().int(),
  status: z.enum(['active', 'pending_approval']),
})

export type ListProviderSlotsResponse = z.infer<typeof ListProviderSlotsResponseSchema>
export type TestProviderCredentialsResponse = z.infer<typeof TestProviderCredentialsResponseSchema>
export type WriteProviderCredentialsResponse = z.infer<typeof WriteProviderCredentialsResponseSchema>
