import { useTranslation } from 'react-i18next'
import { X } from 'lucide-react'
import { SearchInput } from '@/platform/data-table/search-input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/platform/components/ui/select'
import { Button } from '@/platform/components/ui/button'
import { Badge } from '@/platform/components/ui/badge'

export const STATUS_FILTER_ALL = '__all__'

export type StatusFilterOption = { value: string; label: string }

/**
 * The toolbar row of §28.4's List Template -- search + the baseline
 * Status filter (its options declared per entity, §28.8 rule 5, never
 * assumed uniform) + an "N filters active" indicator with one-click
 * Clear All, closing the gap §1.2's CRUD Journey documented in Old
 * Admin (no filter-count affordance, no clear-all). `extraFilters` is
 * an additive slot (§28.8 rule 3) for entity-specific filters beyond
 * the baseline -- rendered, but never counted or cleared by this
 * component itself, since it has no way to know their shape; a caller
 * using extraFilters is responsible for folding its own active count
 * into `activeFilterCount` and its own reset into `onClearAll`.
 */
export function FilterToolbar({
  search,
  onSearchChange,
  searchPlaceholder,
  status,
  onStatusChange,
  statusOptions,
  statusLabel,
  activeFilterCount,
  onClearAll,
  extraFilters,
}: {
  search: string
  onSearchChange: (value: string) => void
  searchPlaceholder: string
  status: string
  onStatusChange: (value: string) => void
  statusOptions: StatusFilterOption[]
  statusLabel: string
  activeFilterCount: number
  onClearAll: () => void
  extraFilters?: React.ReactNode
}) {
  const { t } = useTranslation('entity-workspace')

  return (
    <div className="flex flex-wrap items-center gap-3">
      <SearchInput value={search} onChange={onSearchChange} placeholder={searchPlaceholder} />

      <Select value={status} onValueChange={onStatusChange}>
        <SelectTrigger className="w-auto min-w-36" aria-label={statusLabel}>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {statusOptions.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {extraFilters}

      {activeFilterCount > 0 && (
        <div className="flex items-center gap-2">
          <Badge variant="muted">{t('list.filters.activeCount', { count: activeFilterCount })}</Badge>
          <Button variant="ghost" size="sm" onClick={onClearAll} className="gap-1.5">
            <X className="size-3.5" aria-hidden="true" />
            {t('list.filters.clearAll')}
          </Button>
        </div>
      )}
    </div>
  )
}
