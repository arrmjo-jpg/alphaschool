import { z } from 'zod'

/**
 * The Provider Registry's wire contract (Phase F-B,
 * docs/ADMIN_DESIGN_SYSTEM.md §27.13). Mirrors settings.schemas.ts's
 * own display-only-fields-excluded discipline (`key`/`labelKey` split
 * happens client-side, same `t(key, key)` fallback), but is otherwise a
 * genuinely different contract, not a renamed copy -- see §27.2's three
 * real differences from Configuration Platform.
 *
 * `ProviderSlotStatusSchema` deliberately excludes 'checking' -- that
 * value exists only in admin/src/platform/administration/overview-grid.tsx's
 * own broader OverviewGridStatus type, a client-only transient loading
 * state HealthCheckRunner's synchronous v1 API never returns (§27.4).
 * The wire contract only ever carries what the backend actually sends.
 */
export const ProviderSlotStatusSchema = z.enum(['ready', 'needs-setup', 'error', 'disabled'])

export const ProviderSlotSchema = z.object({
  key: z.string(),
  owningModule: z.string(),
  status: ProviderSlotStatusSchema,
})

/**
 * §27.4/§27.5's pre-freeze amendment: each credential field's `type`
 * is declared explicitly by the backend (ProviderCredentialFieldDefinition,
 * backend/app/Core/ValueObjects/ProviderCredentialFieldDefinition.php)
 * -- never inferred client-side from the field's name.
 */
export const ProviderCredentialFieldTypeSchema = z.enum(['text', 'password', 'secret'])

export const ProviderCredentialFieldSchema = z.object({
  name: z.string(),
  type: ProviderCredentialFieldTypeSchema,
})

/**
 * No `value`/`credentials` field exists anywhere in this schema, on
 * purpose -- credential values are never returned by the backend in
 * either direction (§27.2's first real difference from Configuration
 * Platform; ProviderCredential.credentials is `$hidden`). `configured`
 * is the only signal a client ever gets about whether a value exists.
 */
export const ProviderSlotDetailSchema = z.object({
  key: z.string(),
  credentialFields: z.array(ProviderCredentialFieldSchema),
  configured: z.boolean(),
  canEdit: z.boolean(),
  /** Whether the resolved Provider implements TestsCredentials -- absent, not disabled, when false (§27.5's "never a fake control" rule). */
  canTest: z.boolean(),
  /** The optimistic-locking token ProviderCredentialVault::write()'s `$expectedVersion` requires back (ADR-0019 Decision 5, reusing ADR-0018 Decision 8). */
  version: z.number().int(),
})

export type ProviderSlotStatus = z.infer<typeof ProviderSlotStatusSchema>
export type ProviderSlot = z.infer<typeof ProviderSlotSchema>
export type ProviderCredentialFieldType = z.infer<typeof ProviderCredentialFieldTypeSchema>
export type ProviderCredentialField = z.infer<typeof ProviderCredentialFieldSchema>
export type ProviderSlotDetail = z.infer<typeof ProviderSlotDetailSchema>
