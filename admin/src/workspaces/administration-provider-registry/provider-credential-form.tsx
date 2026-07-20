import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { getProviderRegistryDataProvider } from '@/platform/administration/provider-registry-provider'
import { ApiError } from '@/lib/api-client'
import { ProviderCredentialField } from '@/workspaces/administration-provider-registry/provider-credential-field'
import { StickyActionBar } from '@/platform/components/ui/sticky-action-bar'
import { Button } from '@/platform/components/ui/button'
import { Badge } from '@/platform/components/ui/badge'
import { Skeleton } from '@/platform/components/ui/skeleton'

/**
 * §27.5's credential form -- one atomic all-or-nothing write, no rail,
 * no per-field independent save (contrast Configuration Platform's
 * SettingsCategoryDetail, which this deliberately does not copy).
 *
 * The Edit->Test->Save sequencing (§27.5/§27.7) is the one real
 * interaction this workspace has that Configuration Platform's design
 * never needed: Test Connection sends the currently-typed, unsaved
 * values to `testCredentials`, which never writes anything regardless
 * of the result, so a manager can correct a bad value before it's ever
 * persisted.
 */
export function ProviderCredentialForm({ slotKey }: { slotKey: string }) {
  const { t } = useTranslation('administration-provider-registry')
  const queryClient = useQueryClient()
  const provider = getProviderRegistryDataProvider()

  const { data: detail, isLoading } = useQuery({
    queryKey: ['provider-registry-slot', slotKey],
    queryFn: () => provider!.fetchProviderSlotDetail(slotKey),
    enabled: provider !== null,
  })

  const [pending, setPending] = useState<Record<string, string>>({})
  const [testResult, setTestResult] = useState<{ ok: boolean; message?: string } | null>(null)

  const allFieldsFilled = (detail?.credentialFields ?? []).every((f) => (pending[f.name] ?? '').trim() !== '')

  const testMutation = useMutation({
    mutationFn: () => provider!.testCredentials(slotKey, pending),
    onSuccess: (result) => setTestResult(result),
  })

  const saveMutation = useMutation({
    mutationFn: () => provider!.writeCredentials(slotKey, pending, detail?.version ?? 0),
    onSuccess: () => {
      setPending({})
      setTestResult(null)
      queryClient.invalidateQueries({ queryKey: ['provider-registry-slot', slotKey] })
      queryClient.invalidateQueries({ queryKey: ['provider-registry-slots'] })
    },
    onError: () => {
      // A conflict (409) means someone else changed this slot's
      // credentials first -- refetch so the form reflects the real
      // current state rather than letting a retry target a stale version.
      queryClient.invalidateQueries({ queryKey: ['provider-registry-slot', slotKey] })
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
  const errorMessage = saveMutation.error instanceof ApiError ? saveMutation.error.message : null
  const canEdit = detail?.canEdit ?? false

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-4 rounded-md border bg-card p-4 text-card-foreground">
        {(detail?.credentialFields ?? []).map((field) => (
          <ProviderCredentialField
            key={field.name}
            field={field}
            value={pending[field.name] ?? ''}
            configured={detail?.configured ?? false}
            disabled={!canEdit}
            onChange={(value) => {
              setPending((prev) => ({ ...prev, [field.name]: value }))
              setTestResult(null)
            }}
          />
        ))}
      </div>

      {!canEdit && <p className="text-xs text-muted-foreground">{t('form.viewOnlyNote')}</p>}

      {canEdit && (
        <div className="flex items-center gap-3">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!allFieldsFilled || testMutation.isPending}
            onClick={() => testMutation.mutate()}
          >
            {testMutation.isPending ? t('form.testing') : t('form.testConnection')}
          </Button>
          {testResult && (
            <Badge variant={testResult.ok ? 'success' : 'destructive'}>
              {testResult.ok ? t('form.testOk') : (testResult.message ?? t('form.testFailed'))}
            </Badge>
          )}
        </div>
      )}

      {errorMessage && <p className="text-sm text-destructive">{errorMessage}</p>}

      {canEdit && hasPending && (
        <StickyActionBar>
          <Button
            variant="ghost"
            onClick={() => {
              setPending({})
              setTestResult(null)
            }}
            disabled={saveMutation.isPending}
          >
            {t('form.cancel')}
          </Button>
          <Button onClick={() => saveMutation.mutate()} disabled={!allFieldsFilled || saveMutation.isPending}>
            {saveMutation.isPending ? t('form.saving') : t('form.save')}
          </Button>
        </StickyActionBar>
      )}
    </div>
  )
}
