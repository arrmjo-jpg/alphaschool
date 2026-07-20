import type { LucideIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/platform/components/ui/badge'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The Overview Grid Pattern (docs/ADMIN_DESIGN_SYSTEM.md §26.16) --
 * promoted here from its original home inside
 * workspaces/administration-configuration/ (Configuration Platform's
 * own first consumer) to a shared platform location once Provider
 * Registry became its second real consumer (§27.8), confirming it as a
 * genuine cross-workspace pattern rather than a one-off. Configuration
 * Platform's own usage was updated to this import, not duplicated.
 *
 * `status` is deliberately broader than any single workspace's own wire
 * contract -- `checking` exists only here, at the presentation layer,
 * for a workspace (Provider Registry, §27.4) whose status fetch can be
 * meaningfully "in flight" client-side; a workspace like Configuration
 * Platform, whose wire `SettingCategoryStatus` never includes it, still
 * satisfies this type structurally without any change on its side.
 */
export type OverviewGridStatus = 'ready' | 'needs-setup' | 'error' | 'disabled' | 'checking'

export type OverviewGridItem = {
  key: string
  labelKey: string
  icon: LucideIcon
  status: OverviewGridStatus
  secondaryLine?: string
}

const STATUS_VARIANT: Record<OverviewGridStatus, 'success' | 'warning' | 'destructive' | 'muted'> = {
  ready: 'success',
  'needs-setup': 'warning',
  error: 'destructive',
  disabled: 'muted',
  checking: 'muted',
}

export function OverviewGrid<T extends OverviewGridItem>({
  items,
  onSelect,
  translationNamespace,
}: {
  items: T[]
  onSelect: (key: string) => void
  /** Which i18next namespace resolves each item's labelKey and status.* keys -- the grid itself has no fixed namespace, each workspace owns its own translations. */
  translationNamespace: string
}) {
  const { t } = useTranslation(translationNamespace)

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6">
      {items.map((item) => {
        const Icon = item.icon
        const disabled = item.status === 'disabled'
        return (
          <button
            key={item.key}
            type="button"
            disabled={disabled}
            onClick={() => onSelect(item.key)}
            className={cn(
              'flex flex-col items-center gap-3 rounded-none border border-border bg-card p-6 text-center text-card-foreground shadow-soft outline-none transition-[transform,box-shadow] focus-visible:ring-2 focus-visible:ring-ring',
              disabled
                ? 'cursor-not-allowed opacity-60'
                : 'hover:-translate-y-0.5 hover:shadow-soft-lg',
            )}
          >
            <Icon className={ICON_SIZE.prominent} aria-hidden="true" />
            <span className="text-sm font-medium">{t(item.labelKey, item.key)}</span>
            <Badge variant={STATUS_VARIANT[item.status]}>{t(`status.${item.status}`)}</Badge>
            {item.secondaryLine && <span className="text-xs text-muted-foreground">{item.secondaryLine}</span>}
          </button>
        )
      })}
    </div>
  )
}
