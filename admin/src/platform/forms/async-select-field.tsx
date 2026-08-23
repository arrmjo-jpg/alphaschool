import { useQuery } from '@tanstack/react-query'
import type { Control, FieldValues, Path } from 'react-hook-form'
import { SelectField } from '@/platform/forms/select-field'

/**
 * A 'select' field whose options come from a live query rather than a
 * static array declared at module load time (§28.8's EntityField
 * 'async-select' kind, first real consumer: Term's academic_year_id,
 * UI Sprint 1-B). Renders the existing SelectField once options
 * resolve -- no new select UI, just a live data source in front of it.
 */
export function AsyncSelectField<T extends FieldValues>({
  control,
  name,
  label,
  loadOptions,
  queryKey,
}: {
  control: Control<T>
  name: Path<T>
  label: string
  loadOptions: () => Promise<{ value: string; label: string }[]>
  queryKey: unknown[]
}) {
  const { data: options = [], isLoading } = useQuery({ queryKey, queryFn: loadOptions })

  return (
    <SelectField
      control={control}
      name={name}
      label={label}
      options={options}
      placeholder={isLoading ? 'Loading…' : undefined}
    />
  )
}
