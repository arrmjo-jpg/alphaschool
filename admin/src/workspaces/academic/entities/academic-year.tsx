import { createColumnHelper } from '@tanstack/react-table'
import { z } from 'zod'
import { Badge } from '@/platform/components/ui/badge'
import type { EntityDefinition } from '@/platform/entity-workspace/entity-metadata'

export type AcademicYear = {
  id: number
  name_en: string
  name_ar: string
  start_date: string
  end_date: string
  status: 'upcoming' | 'active' | 'closed'
}

const columnHelper = createColumnHelper<AcademicYear>()

const STATUS_VARIANT = { upcoming: 'default', active: 'success', closed: 'muted' } as const

/**
 * UI Sprint 1-B's first real entity (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- bilingual name + lifecycle3 status, deliberately the
 * simplest real CRUD proof point per the agreed integration order.
 */
export const academicYearEntity: EntityDefinition<AcademicYear> = {
  key: 'academic-years',
  labelSingular: 'Academic Year',
  labelPlural: 'Academic Years',
  fields: [
    { kind: 'bilingual-name', nameEnField: 'name_en', nameArField: 'name_ar', labelEn: 'Name (English)', labelAr: 'Name (Arabic)' },
    { kind: 'date', name: 'start_date', label: 'Start Date' },
    { kind: 'date', name: 'end_date', label: 'End Date' },
  ],
  columns: [
    columnHelper.accessor('name_en', { header: 'Name (EN)' }),
    columnHelper.accessor('name_ar', { header: 'Name (AR)' }),
    columnHelper.accessor('start_date', { header: 'Start Date', cell: (info) => String(info.getValue()).slice(0, 10) }),
    columnHelper.accessor('end_date', { header: 'End Date', cell: (info) => String(info.getValue()).slice(0, 10) }),
    columnHelper.accessor('status', {
      header: 'Status',
      cell: (info) => {
        const value = info.getValue() as AcademicYear['status']
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
    name_en: z.string().min(1),
    name_ar: z.string().min(1),
    start_date: z.string().min(1),
    end_date: z.string().min(1),
  }),
  displayName: (row) => row.name_en,
}
