import { createColumnHelper } from '@tanstack/react-table'
import { z } from 'zod'
import { Badge } from '@/platform/components/ui/badge'
import type { EntityDefinition } from '@/platform/entity-workspace/entity-metadata'

export type GradeLevel = {
  id: number
  code: string
  name_en: string
  name_ar: string
  sequence_order: number
  is_active: boolean
}

const columnHelper = createColumnHelper<GradeLevel>()

/**
 * UI Sprint 1-B's second entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * exercises the boolean statusType branch for the first time
 * (AcademicYear was lifecycle3), with zero changes needed to
 * entity-workspace/'s shared shells to support it.
 */
export const gradeLevelEntity: EntityDefinition<GradeLevel> = {
  key: 'grade-levels',
  labelSingular: 'Grade Level',
  labelPlural: 'Grade Levels',
  fields: [
    { kind: 'text', name: 'code', label: 'Code' },
    { kind: 'bilingual-name', nameEnField: 'name_en', nameArField: 'name_ar', labelEn: 'Name (English)', labelAr: 'Name (Arabic)' },
    { kind: 'text', name: 'sequence_order', label: 'Sequence Order', type: 'number' },
  ],
  columns: [
    columnHelper.accessor('code', { header: 'Code' }),
    columnHelper.accessor('name_en', { header: 'Name (EN)' }),
    columnHelper.accessor('name_ar', { header: 'Name (AR)' }),
    columnHelper.accessor('sequence_order', { header: 'Order' }),
    columnHelper.accessor('is_active', {
      header: 'Status',
      cell: (info) => <Badge variant={info.getValue() ? 'success' : 'muted'}>{info.getValue() ? 'Active' : 'Inactive'}</Badge>,
    }),
  ],
  status: {
    type: 'boolean',
    field: 'is_active',
    activeLabel: 'Active',
    inactiveLabel: 'Inactive',
    deactivateActionLabel: 'Deactivate',
    activateActionLabel: 'Activate',
  },
  capabilities: { canCreate: true, canEdit: true, canDeactivate: true, hasDetail: true, hasBulkActions: false },
  schema: z.object({
    code: z.string().min(1),
    name_en: z.string().min(1),
    name_ar: z.string().min(1),
    sequence_order: z.coerce.number().int().min(1),
  }),
  displayName: (row) => row.name_en,
}
