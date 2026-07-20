// TEMPORARY re-verification fixture -- proving Test genuinely never
// touches persisted state, not just that the UI doesn't transition to
// a saved-looking state. Reverted immediately after this check.
import type {
  ProviderRegistryDataProvider,
  ProviderSlot,
  ProviderSlotDetail,
} from '@/platform/administration/provider-registry-provider'

export function buildFixtureProvider(icons: { KeyRound: ProviderSlot['icon'] }): ProviderRegistryDataProvider {
  const slots: ProviderSlot[] = [
    { key: 'identity.federation.google-oauth', labelKey: 'fixture.googleOauth', icon: icons.KeyRound, status: 'needs-setup', secondaryLine: 'Identity' },
  ]

  const details: Record<string, ProviderSlotDetail> = {
    'identity.federation.google-oauth': {
      key: 'identity.federation.google-oauth',
      credentialFields: [
        { name: 'client_id', type: 'text' },
        { name: 'client_secret', type: 'secret' },
      ],
      configured: false,
      canEdit: true,
      version: 0,
    },
  }

  let testCallCount = 0

  return {
    fetchProviderSlots: async () => slots,
    fetchProviderSlotDetail: async (slotKey) => {
      if (!(slotKey in details)) throw new Error(`Fixture has no detail for ${slotKey}`)
      return details[slotKey]
    },
    testCredentials: async (_slotKey, _credentials) => {
      testCallCount += 1
      // biome-ignore lint: fixture-only diagnostic
      ;(window as unknown as { __testCallCount: number }).__testCallCount = testCallCount
      await new Promise((r) => setTimeout(r, 300))
      return { ok: true }
    },
    writeCredentials: async (slotKey, _credentials, expectedVersion) => {
      const current = details[slotKey]
      if (current.version !== expectedVersion) {
        throw new Error('Fixture: version conflict')
      }
      details[slotKey] = { ...current, configured: true, version: current.version + 1 }
      return { version: current.version + 1, status: 'active' }
    },
  }
}
