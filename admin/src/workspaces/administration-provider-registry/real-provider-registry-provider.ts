import { Mail, KeyRound, Bell, HardDrive, Plug, type LucideIcon } from 'lucide-react'
import {
  ListProviderSlotsResponseSchema,
  ProviderSlotDetailSchema,
  TestProviderCredentialsResponseSchema,
  TestProviderCredentialsRequestSchema,
  WriteProviderCredentialsResponseSchema,
  WriteProviderCredentialsRequestSchema,
} from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { ProviderRegistryDataProvider, ProviderSlot, ProviderSlotDetail } from '@/platform/administration/provider-registry-provider'

/**
 * The real Phase F-B provider (docs/ADMIN_DESIGN_SYSTEM.md §27.13) --
 * mirrors real-configuration-provider.ts's own discipline exactly:
 * every response validated through @alphaschool/contracts' Zod schemas
 * before use (ADR-0023 Decision 3), icon/labelKey enrichment lives
 * here (the backend's ProviderRegistration model has no display
 * metadata, by design), an unrecognized slot key still renders with a
 * sensible fallback rather than crashing.
 */
const SLOT_ICONS: Record<string, LucideIcon> = {
  'notifications.email.smtp': Mail,
  'identity.federation.google-oauth': KeyRound,
  'notifications.push.firebase': Bell,
  'media.storage.r2': HardDrive,
}

const DEFAULT_SLOT_ICON: LucideIcon = Plug

export const realProviderRegistryDataProvider: ProviderRegistryDataProvider = {
  async fetchProviderSlots(): Promise<ProviderSlot[]> {
    const raw = await apiFetch('/administration/providers')
    const { slots } = ListProviderSlotsResponseSchema.parse(raw)

    return slots.map((slot) => ({
      key: slot.key,
      labelKey: `administration-provider-registry:slot.${slot.key}`,
      icon: SLOT_ICONS[slot.key] ?? DEFAULT_SLOT_ICON,
      status: slot.status,
      secondaryLine: slot.owningModule,
    }))
  },

  async fetchProviderSlotDetail(slotKey: string): Promise<ProviderSlotDetail> {
    const raw = await apiFetch(`/administration/providers/${slotKey}`)

    return ProviderSlotDetailSchema.parse(raw)
  },

  async testCredentials(slotKey, credentials) {
    const body = TestProviderCredentialsRequestSchema.parse({ credentials })
    const raw = await apiFetch(`/administration/providers/${slotKey}/test`, {
      method: 'POST',
      body,
    })

    return TestProviderCredentialsResponseSchema.parse(raw)
  },

  async writeCredentials(slotKey, credentials, expectedVersion) {
    const body = WriteProviderCredentialsRequestSchema.parse({ credentials, expectedVersion })
    const raw = await apiFetch(`/administration/providers/${slotKey}`, {
      method: 'PATCH',
      body,
    })

    return WriteProviderCredentialsResponseSchema.parse(raw)
  },
}
