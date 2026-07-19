import { useTranslation } from 'react-i18next'
import type { SettingCategory, SettingCategoryStatus } from '@/platform/administration/configuration-provider'
import { Badge } from '@/platform/components/ui/badge'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The System Settings landing page (a UX refinement on top of Phase
 * E-A, not a supersession -- the two-pane rail+detail interface is
 * unchanged, it simply stops being the first thing a user sees).
 * Deliberately card-grid-shaped, never dashboard-shaped: no charts, no
 * counters, no stats -- name, one status badge, an optional one-line
 * secondary note, nothing else. Modern Settings-surface precedent
 * (Apple/Linear/Notion/Stripe), not an analytics precedent.
 *
 * Two explicit, scoped exceptions to previously-frozen tokens, both
 * requested deliberately for this card family specifically, not a
 * silent drift: `rounded-none` (§23.2's enterprise radius scale caps
 * cards at 4-6px, never square) and the hover lift (§4.4 explicitly
 * froze "no scale/translate hover effects anywhere" as a considered
 * rule). Recorded in docs/ADMIN_DESIGN_SYSTEM.md as named amendments
 * rather than left as an undocumented mismatch between code and doc.
 */
const STATUS_VARIANT: Record<SettingCategoryStatus, 'success' | 'warning' | 'destructive' | 'muted'> = {
  ready: 'success',
  'needs-setup': 'warning',
  error: 'destructive',
  disabled: 'muted',
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
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6">
      {categories.map((category) => {
        const Icon = category.icon
        const disabled = category.status === 'disabled'
        return (
          <button
            key={category.key}
            type="button"
            disabled={disabled}
            onClick={() => onSelect(category.key)}
            className={cn(
              'flex flex-col items-center gap-3 rounded-none border border-border bg-card p-6 text-center text-card-foreground shadow-soft outline-none transition-[transform,box-shadow] focus-visible:ring-2 focus-visible:ring-ring',
              disabled
                ? 'cursor-not-allowed opacity-60'
                : 'hover:-translate-y-0.5 hover:shadow-soft-lg',
            )}
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
