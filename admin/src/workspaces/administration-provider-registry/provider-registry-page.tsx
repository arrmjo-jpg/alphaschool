import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, PlugZap, Inbox } from 'lucide-react'
import { getProviderRegistryDataProvider } from '@/platform/administration/provider-registry-provider'
import { OverviewGrid } from '@/platform/administration/overview-grid'
import { ProviderCredentialForm } from '@/workspaces/administration-provider-registry/provider-credential-form'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { Button } from '@/platform/components/ui/button'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The Provider Registry reference page (§27.3) -- Overview Grid ->
 * credential form directly, deliberately no intermediate rail (contrast
 * ConfigurationPlatformPage's two-pane rail+detail): a provider slot is
 * one atomic credential set, not a category with independently
 * browsable fields, so a rail would add a click with no destination
 * behind it (§27.2's third real difference from Configuration Platform).
 */
export default function ProviderRegistryPage() {
  const { t } = useTranslation('administration-provider-registry')
  const provider = getProviderRegistryDataProvider()
  const [selectedSlot, setSelectedSlot] = useState<string | null>(null)

  const { data: slots, isLoading } = useQuery({
    queryKey: ['provider-registry-slots'],
    queryFn: () => provider!.fetchProviderSlots(),
    enabled: provider !== null,
  })

  return (
    <div className="flex flex-col gap-6">
      <WorkspaceHeader title={t('workspace.label')} />

      {provider === null ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
          <PlugZap className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
          <h2 className="text-base font-medium">{t('slots.notConnected.title')}</h2>
          <p className="max-w-sm text-sm text-muted-foreground">{t('slots.notConnected.body')}</p>
        </div>
      ) : isLoading ? null : (slots ?? []).length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
          <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
          <h2 className="text-base font-medium">{t('slots.empty.title')}</h2>
          <p className="max-w-sm text-sm text-muted-foreground">{t('slots.empty.body')}</p>
        </div>
      ) : selectedSlot === null ? (
        <OverviewGrid items={slots!} onSelect={setSelectedSlot} translationNamespace="administration-provider-registry" />
      ) : (
        <div className="min-w-0 flex-1">
          <Button variant="ghost" size="sm" onClick={() => setSelectedSlot(null)} className="mb-3 gap-1.5">
            <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
            {t('workspace.label')}
          </Button>
          <ProviderCredentialForm slotKey={selectedSlot} />
        </div>
      )}
    </div>
  )
}
