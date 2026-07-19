import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { getConfigurationDataProvider } from '@/platform/administration/configuration-provider'
import { SettingField } from '@/workspaces/administration-configuration/setting-field'
import { StickyActionBar } from '@/platform/components/ui/sticky-action-bar'
import { Button } from '@/platform/components/ui/button'
import { Skeleton } from '@/platform/components/ui/skeleton'

/**
 * The card-sectioned settings form (§26.5) -- a card per category's
 * field set, StickyActionBar save. Pending edits are local component
 * state until saved; nothing writes optimistically, matching
 * SettingsResolver's own optimistic-locking write contract (once
 * Phase E-B exposes it) rather than assuming a write always succeeds.
 */
export function SettingsCategoryDetail({ categoryKey }: { categoryKey: string }) {
  const { t } = useTranslation('administration-configuration')
  const queryClient = useQueryClient()
  const provider = getConfigurationDataProvider()

  const { data: fields, isLoading } = useQuery({
    queryKey: ['configuration-category-settings', categoryKey],
    queryFn: () => provider!.fetchCategorySettings(categoryKey),
    enabled: provider !== null,
  })

  const [pending, setPending] = useState<Record<string, unknown>>({})

  useEffect(() => setPending({}), [categoryKey])

  const mutation = useMutation({
    mutationFn: async () => {
      for (const [fieldKey, value] of Object.entries(pending)) {
        await provider!.writeSetting(categoryKey, fieldKey, value)
      }
    },
    onSuccess: () => {
      setPending({})
      queryClient.invalidateQueries({ queryKey: ['configuration-category-settings', categoryKey] })
    },
  })

  if (isLoading) {
    return (
      <div className="flex flex-col gap-3">
        <Skeleton className="h-6 w-48" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-10 w-full" />
      </div>
    )
  }

  const hasPending = Object.keys(pending).length > 0

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-md border bg-card p-4 text-card-foreground">
        {(fields ?? []).map((field) => (
          <SettingField
            key={field.key}
            field={{ ...field, value: field.key in pending ? pending[field.key] : field.value }}
            onChange={(value) => setPending((prev) => ({ ...prev, [field.key]: value }))}
          />
        ))}
      </div>

      {hasPending && (
        <StickyActionBar>
          <Button variant="ghost" onClick={() => setPending({})} disabled={mutation.isPending}>
            {t('detail.cancel')}
          </Button>
          <Button onClick={() => mutation.mutate()} disabled={mutation.isPending}>
            {mutation.isPending ? t('detail.saving') : t('detail.save')}
          </Button>
        </StickyActionBar>
      )}
    </div>
  )
}
