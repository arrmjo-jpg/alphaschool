import { createColumnHelper } from '@tanstack/react-table'
import { z } from 'zod'
import { Badge } from '@/platform/components/ui/badge'
import type { EntityDefinition } from '@/platform/entity-workspace/entity-metadata'
import { academicYearProvider } from '@/workspaces/academic/providers/academic-year-provider'

export type Term = {
  id: number
  academic_year_id: number
  name_en: string
  name_ar: string
  sequence_order: number
  start_date: string
  end_date: string
  status: 'upcoming' | 'active' | 'closed'
}

const columnHelper = createColumnHelper<Term>()

const STATUS_VARIANT = { upcoming: 'default', active: 'success', closed: 'muted' } as const

/**
 * Shared by both the Form field (async-select) and the List's
 * extraFilters -- one real Academic Year list call, mapped once,
 * never two parallel implementations of "how to fetch years."
 */
async function loadAcademicYearOptions() {
  const page = await academicYearProvider.list({ page: 1, perPage: 100, sort: '-start_date', search: '', status: null, extra: {} })
  return page.data.map((year) => ({ value: String(year.id), label: year.name_en }))
}

/**
 * UI Sprint 1-B's fourth entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * the first real FK-scoped entity and first real consumer of the
 * 'async-select' field kind and the extraFilters slot (§28.8 rule 3),
 * both additive to entity-workspace/'s shared shells, not forks.
 */
export const termEntity: EntityDefinition<Term> = {
  key: 'terms',
  labelSingular: 'Term',
  labelPlural: 'Terms',
  fields: [
    { kind: 'async-select', name: 'academic_year_id', label: 'Academic Year', loadOptions: loadAcademicYearOptions, queryKey: ['academic-years', 'options'] },
    { kind: 'bilingual-name', nameEnField: 'name_en', nameArField: 'name_ar', labelEn: 'Name (English)', labelAr: 'Name (Arabic)' },
    { kind: 'text', name: 'sequence_order', label: 'Sequence Order', type: 'number' },
    { kind: 'date', name: 'start_date', label: 'Start Date' },
    { kind: 'date', name: 'end_date', label: 'End Date' },
  ],
  columns: [
    columnHelper.accessor('name_en', { header: 'Name (EN)' }),
    columnHelper.accessor('name_ar', { header: 'Name (AR)' }),
    columnHelper.accessor('sequence_order', { header: 'Order' }),
    columnHelper.accessor('start_date', { header: 'Start Date', cell: (info) => String(info.getValue()).slice(0, 10) }),
    columnHelper.accessor('end_date', { header: 'End Date', cell: (info) => String(info.getValue()).slice(0, 10) }),
    columnHelper.accessor('status', {
      header: 'Status',
      cell: (info) => {
        const value = info.getValue() as Term['status']
        return <Badge variant={STATUS_VARIANT[value]}>{value}</Badge>
      },
    }),
  ],
  status: {
    type: 'lifecycle3',
    field: 'status',
    values: ['upcoming', 'active', 'closed'] as const,
    valueLabels: { upcoming: 'Upcoming', active: 'Active', closed: 'Closed' },
    closeActionLabel: 'Close',
  },
  capabilities: { canCreate: true, canEdit: true, canDeactivate: true, hasDetail: true, hasBulkActions: false },
  schema: z.object({
    academic_year_id: z.string().min(1),
    name_en: z.string().min(1),
    name_ar: z.string().min(1),
    sequence_order: z.coerce.number().int().min(1),
    start_date: z.string().min(1),
    end_date: z.string().min(1),
  }),
  displayName: (row) => row.name_en,
  extraFilters: [{ key: 'academic_year_id', label: 'Academic Year', loadOptions: loadAcademicYearOptions, queryKey: ['academic-years', 'options'] }],
}
