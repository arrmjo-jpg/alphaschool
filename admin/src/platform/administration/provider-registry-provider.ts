import type { LucideIcon } from 'lucide-react'
import type { OverviewGridStatus } from '@/platform/administration/overview-grid'

/**
 * The Provider Registry data contract (docs/ADMIN_DESIGN_SYSTEM.md §27),
 * mirroring configuration-provider.ts's "zero registered = honestly not
 * connected" pattern exactly (§27.1's own stated reuse of every proven
 * Configuration Platform mechanism). Three real differences from that
 * contract, all resolved during §27's pre-freeze review, not incidental:
 *
 * 1. No `value` field exists anywhere in this contract, on purpose --
 *    credential values are never returned by the backend in either
 *    direction (§27.2), so there is nothing for this type to carry.
 * 2. `ProviderCredentialFieldDefinition.type` is backend-declared, never
 *    inferred from the field's name client-side (§27.4/§27.5's own
 *    rejection of a name heuristic that would only ever grow).
 * 3. `testCredentials` exists alongside `writeCredentials` specifically
 *    so the Edit->Test->Save flow (§27.5/§27.7) never has to persist a
 *    value just to find out whether it's valid.
 */

export type ProviderCredentialFieldType = 'text' | 'password' | 'secret'

export type ProviderCredentialFieldDefinition = {
  name: string
  type: ProviderCredentialFieldType
}

export type ProviderSlot = {
  key: string
  labelKey: string
  icon: LucideIcon
  status: OverviewGridStatus
  secondaryLine?: string
}

export type ProviderSlotDetail = {
  key: string
  credentialFields: ProviderCredentialFieldDefinition[]
  /** Slot-level, not per-field -- ProviderCredentialVault::write()'s assertCredentialShape() requires the exact declared field set on every write, so a slot's credentials are configured or not as one atomic unit, never partially. Drives every field's "configured"/"not set" placeholder identically. */
  configured: boolean
  /** §27.6: the write endpoint (ProviderCredentialVault::assertCanEdit()) is the real gate; this is an accurate preview of it, never a promise beyond it. */
  canEdit: boolean
  /** Current row's version for optimistic locking (ADR-0019 Decision 5, reusing ADR-0018 Decision 8's algorithm) -- always versioned, never a non-versioned path. */
  version: number
}

export type TestCredentialsResult = {
  ok: boolean
  message?: string
}

export type WriteCredentialsResponse = {
  version: number
  status: 'active' | 'pending_approval'
}

export type ProviderRegistryDataProvider = {
  /** Expected to already be visibility-filtered per §27.6 -- general Administration access, no per-slot view permission to check. */
  fetchProviderSlots: () => Promise<ProviderSlot[]>
  fetchProviderSlotDetail: (slotKey: string) => Promise<ProviderSlotDetail>
  /**
   * §27.5's binding rule: never persists. Tests the given, in-memory
   * credential values directly -- the backend contract this requires
   * (a TestsCredentials sibling to HealthCheckable) is Phase F-B scope;
   * this shape is what F-B's real implementation must satisfy.
   */
  testCredentials: (slotKey: string, credentials: Record<string, string>) => Promise<TestCredentialsResult>
  writeCredentials: (
    slotKey: string,
    credentials: Record<string, string>,
    expectedVersion: number,
  ) => Promise<WriteCredentialsResponse>
}

let activeProvider: ProviderRegistryDataProvider | null = null

export function setProviderRegistryDataProvider(provider: ProviderRegistryDataProvider): void {
  activeProvider = provider
}

export function getProviderRegistryDataProvider(): ProviderRegistryDataProvider | null {
  return activeProvider
}
