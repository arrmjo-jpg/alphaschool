import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, PlugZap, Inbox } from 'lucide-react'
import { getConfigurationDataProvider } from '@/platform/administration/configuration-provider'
import { OverviewGrid } from '@/platform/administration/overview-grid'
import { SettingsCategoryList } from '@/workspaces/administration-configuration/settings-category-list'
import { SettingsCategoryDetail } from '@/workspaces/administration-configuration/settings-category-detail'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { Button } from '@/platform/components/ui/button'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The Configuration Platform reference page (§26.3/§26.5/§26.10),
 * revised to land on a card-grid overview (a UX refinement, not a
 * supersession of the two-pane rail+detail interface -- that interface
 * is unchanged, it simply stops being the first thing shown). Three
 * genuinely different states, not one generic "nothing to show": no
 * provider registered means the real backend integration (Phase E-B)
 * has not shipped -- an honest "not connected" state; zero categories
 * once a provider *is* registered is a separate, later state; and
 * "categories exist, none selected yet" is now the Overview grid,
 * never the rail.
 */
export default function ConfigurationPlatformPage() {
  const { t } = useTranslation('administration-configuration')
  const provider = getConfigurationDataProvider()
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null)

  const { data: categories, isLoading } = useQuery({
    queryKey: ['configuration-categories'],
    queryFn: () => provider!.fetchCategories(),
    enabled: provider !== null,
  })

  return (
    <div className="flex flex-col gap-6">
      <WorkspaceHeader title={t('workspace.label')} />

      {provider === null ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
          <PlugZap className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
          <h2 className="text-base font-medium">{t('categories.notConnected.title')}</h2>
          <p className="max-w-sm text-sm text-muted-foreground">{t('categories.notConnected.body')}</p>
        </div>
      ) : isLoading ? null : (categories ?? []).length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-center">
          <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
          <h2 className="text-base font-medium">{t('categories.empty.title')}</h2>
          <p className="max-w-sm text-sm text-muted-foreground">{t('categories.empty.body')}</p>
        </div>
      ) : selectedCategory === null ? (
        <OverviewGrid items={categories!} onSelect={setSelectedCategory} translationNamespace="administration-configuration" />
      ) : (
        <div className="flex flex-col gap-6 lg:flex-row">
          <div className="hidden lg:block lg:w-56 lg:shrink-0">
            <SettingsCategoryList
              categories={categories!}
              selectedKey={selectedCategory}
              onSelect={setSelectedCategory}
            />
          </div>

          <div className="min-w-0 flex-1">
            <Button variant="ghost" size="sm" onClick={() => setSelectedCategory(null)} className="mb-3 gap-1.5">
              <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
              {t('workspace.label')}
            </Button>
            <SettingsCategoryDetail categoryKey={selectedCategory} />
          </div>
        </div>
      )}
    </div>
  )
}
