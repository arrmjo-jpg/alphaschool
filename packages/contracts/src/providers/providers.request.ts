import { z } from 'zod'

/**
 * §27.5's Edit->Test->Save rule: deliberately no `expectedVersion`
 * field -- testing never touches ProviderCredentialVault's
 * optimistic-locking write path at all, so there is no version to
 * reconcile against. Contrast WriteProviderCredentialsRequestSchema
 * below, which mirrors WriteSettingRequestSchema's mandatory version.
 */
export const TestProviderCredentialsRequestSchema = z.object({
  credentials: z.record(z.string(), z.unknown()),
})

export const WriteProviderCredentialsRequestSchema = z.object({
  credentials: z.record(z.string(), z.unknown()),
  expectedVersion: z.number().int(),
})

export type TestProviderCredentialsRequest = z.infer<typeof TestProviderCredentialsRequestSchema>
export type WriteProviderCredentialsRequest = z.infer<typeof WriteProviderCredentialsRequestSchema>
