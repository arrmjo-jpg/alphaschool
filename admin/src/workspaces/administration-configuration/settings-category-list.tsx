import { useTranslation } from 'react-i18next'
import type { SettingCategory } from '@/platform/administration/configuration-provider'
import { cn } from '@/lib/cn'

/**
 * The category rail (§26.3/§26.10) -- a real nav landmark (§26.11), not
 * a plain list, since it is a navigation surface within the workspace.
 * Categories are assumed already permission-filtered by the provider
 * (§26.6) -- nothing here re-derives visibility.
 */
export function SettingsCategoryList({
  categories,
  selectedKey,
  onSelect,
}: {
  categories: SettingCategory[]
  selectedKey: string | null
  onSelect: (key: string) => void
}) {
  const { t } = useTranslation('administration-configuration')

  return (
    <nav aria-label={t('workspace.label')} className="flex flex-col gap-1">
      {categories.map((category) => (
        <button
          key={category.key}
          type="button"
          onClick={() => onSelect(category.key)}
          aria-current={selectedKey === category.key ? 'page' : undefined}
          className={cn(
            'rounded-sm px-3 py-2 text-start text-sm font-medium outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring',
            selectedKey === category.key
              ? 'bg-primary/10 text-primary'
              : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
          )}
        >
          {t(category.labelKey, category.key)}
        </button>
      ))}
    </nav>
  )
}
