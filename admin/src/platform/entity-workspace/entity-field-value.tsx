import { useQuery } from '@tanstack/react-query'
import type { EntityField } from '@/platform/entity-workspace/entity-metadata'

function AsyncSelectFieldValue({
  label,
  value,
  loadOptions,
  queryKey,
}: {
  label: string
  value: unknown
  loadOptions: () => Promise<{ value: string; label: string }[]>
  queryKey: unknown[]
}) {
  const { data: options = [] } = useQuery({ queryKey, queryFn: loadOptions })
  const display = options.find((o) => o.value === String(value))?.label ?? String(value ?? '—')

  return (
    <div>
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="text-sm">{display}</dd>
    </div>
  )
}

/** Read-only rendering of one declared field (§28.5's Detail pattern) -- shares the same field-config the Form pattern consumes, never a parallel definition. */
export function EntityFieldValue<T>({ field, row }: { field: EntityField<T>; row: T }) {
  const record = row as Record<string, unknown>

  if (field.kind === 'bilingual-name') {
    return (
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <dt className="text-xs font-medium text-muted-foreground">{field.labelEn}</dt>
          <dd className="text-sm">{String(record[field.nameEnField] ?? '—')}</dd>
        </div>
        <div dir="rtl">
          <dt className="text-xs font-medium text-muted-foreground">{field.labelAr}</dt>
          <dd className="text-sm">{String(record[field.nameArField] ?? '—')}</dd>
        </div>
      </div>
    )
  }

  if (field.kind === 'async-select') {
    return <AsyncSelectFieldValue label={field.label} value={record[field.name]} loadOptions={field.loadOptions} queryKey={field.queryKey} />
  }

  const value = record[field.name]
  const display =
    field.kind === 'select'
      ? (field.options.find((o) => o.value === value)?.label ?? String(value ?? '—'))
      : field.kind === 'date' && value
        ? String(value).slice(0, 10)
        : String(value ?? '—')

  return (
    <div>
      <dt className="text-xs font-medium text-muted-foreground">{field.label}</dt>
      <dd className="text-sm">{display}</dd>
    </div>
  )
}
