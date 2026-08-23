import { useQuery } from '@tanstack/react-query'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/platform/components/ui/select'
import { STATUS_FILTER_ALL } from '@/platform/data-table/filter-toolbar'
import type { EntityExtraFilter } from '@/platform/entity-workspace/entity-metadata'

/**
 * Renders one declared EntityExtraFilter (§28.8 rule 3) as a live,
 * query-backed Select -- the List-toolbar analog of AsyncSelectField's
 * form-field version, first real consumer Term's academic_year_id
 * (UI Sprint 1-B).
 */
export function ExtraFilterControl({
  filter,
  value,
  onChange,
  allLabel,
}: {
  filter: EntityExtraFilter
  value: string
  onChange: (value: string) => void
  allLabel: string
}) {
  const { data: options = [] } = useQuery({ queryKey: filter.queryKey, queryFn: filter.loadOptions })

  return (
    <Select value={value} onValueChange={onChange}>
      <SelectTrigger className="w-auto min-w-36" aria-label={filter.label}>
        <SelectValue placeholder={filter.label} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={STATUS_FILTER_ALL}>{allLabel}</SelectItem>
        {options.map((option) => (
          <SelectItem key={option.value} value={option.value}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
