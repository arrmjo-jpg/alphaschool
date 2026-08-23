import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/platform/components/ui/badge'
import { Button } from '@/platform/components/ui/button'
import { isCurrentAsOfToday, type TimelineRow } from '@/platform/timeline/timeline-metadata'
import { cn } from '@/lib/cn'

const STATUS_VARIANT = {
  scheduled: 'default',
  active: 'success',
  ended: 'muted',
  cancelled: 'destructive',
} as const satisfies Record<TimelineRow['status'], 'default' | 'success' | 'muted' | 'destructive'>

/**
 * The reusable List-analog for a HasTemporalAssignment-backed anchor
 * (docs/ADMIN_DESIGN_SYSTEM.md §30.3/§30.7) -- built once for
 * HomeroomAssignment, reused verbatim by SectionAssignment/
 * TeacherAssignment when their own slice is built (ordering,
 * asOf-computed active highlighting, and the four status Badges never
 * change per entity; only `rows`/`reasonCodeLabels` do). Replaces
 * DataTable entirely for this pattern -- read as a whole, never paged.
 */
export function Timeline({
  rows,
  reasonCodeLabels,
  onClose,
  onCancel,
  emptyState,
}: {
  rows: TimelineRow[]
  /** reason code -> display label, resolved by the caller from its own reason-codes options query -- Timeline itself never fetches. */
  reasonCodeLabels: Record<string, string>
  onClose: (row: TimelineRow) => void
  onCancel: (row: TimelineRow) => void
  emptyState: ReactNode
}) {
  const { t } = useTranslation('timeline')

  if (rows.length === 0) {
    return <>{emptyState}</>
  }

  return (
    <div className="flex flex-col gap-3">
      {rows.map((row) => {
        const current = isCurrentAsOfToday(row)
        const cancelled = row.status === 'cancelled'

        return (
          <div
            key={row.id}
            className={cn(
              'flex flex-col gap-2 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between',
              current ? 'border-primary bg-primary/5' : 'opacity-80',
            )}
          >
            <div className="flex flex-col gap-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className={cn('font-medium', cancelled && 'line-through')}>{row.primaryLabel}</span>
                <Badge variant={STATUS_VARIANT[row.status]}>{t(`status.${row.status}`)}</Badge>
              </div>
              <p className={cn('text-sm text-muted-foreground', cancelled && 'line-through')}>
                {row.effective_from} → {row.effective_until ?? t('ongoing')}
              </p>
              {row.reason_code && (
                <p className="text-sm text-muted-foreground">
                  {t('reason')}: {reasonCodeLabels[row.reason_code] ?? row.reason_code}
                </p>
              )}
              {row.ended_by_name && <p className="text-sm text-muted-foreground">{t('endedBy', { name: row.ended_by_name })}</p>}
            </div>

            <div className="flex shrink-0 gap-2">
              {current && (
                <Button variant="outline" size="sm" onClick={() => onClose(row)}>
                  {t('actions.close')}
                </Button>
              )}
              {!cancelled && (
                <Button variant="outline" size="sm" onClick={() => onCancel(row)}>
                  {t('actions.cancel')}
                </Button>
              )}
            </div>
          </div>
        )
      })}
    </div>
  )
}
