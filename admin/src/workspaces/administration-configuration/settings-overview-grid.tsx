import { useTranslation } from 'react-i18next'
import type { SettingCategory, SettingCategoryStatus } from '@/platform/administration/configuration-provider'
import { Badge } from '@/platform/components/ui/badge'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * The System Settings landing page (a UX refinement on top of Phase
 * E-A, not a supersession -- the two-pane rail+detail interface is
 * unchanged, it simply stops being the first thing a user sees).
 * Deliberately card-grid-shaped, never dashboard-shaped: no charts, no
 * counters, no stats -- name, one status badge, an optional one-line
 * secondary note, nothing else. Modern Settings-surface precedent
 * (Apple/Linear/Notion/Stripe), not an analytics precedent.
 */
const STATUS_VARIANT: Record<SettingCategoryStatus, 'success' | 'warning' | 'destructive'> = {
  ready: 'success',
  'needs-setup': 'warning',
  error: 'destructive',
}

export function SettingsOverviewGrid({
  categories,
  onSelect,
}: {
  categories: SettingCategory[]
  onSelect: (key: string) => void
}) {
  const { t } = useTranslation('administration-configuration')

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {categories.map((category) => {
        const Icon = category.icon
        return (
          <button
            key={category.key}
            type="button"
            onClick={() => onSelect(category.key)}
            className="flex flex-col items-start gap-3 rounded-md border border-border bg-card p-6 text-start text-card-foreground outline-none transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
          >
            <Icon className={ICON_SIZE.prominent} aria-hidden="true" />
            <span className="text-sm font-medium">{t(category.labelKey, category.labelKey)}</span>
            <Badge variant={STATUS_VARIANT[category.status]}>{t(`status.${category.status}`)}</Badge>
            {category.secondaryLine && <span className="text-xs text-muted-foreground">{category.secondaryLine}</span>}
          </button>
        )
      })}
    </div>
  )
}
