import { createColumnHelper } from '@tanstack/react-table'
import { z } from 'zod'
import { Badge } from '@/platform/components/ui/badge'
import type { EntityDefinition } from '@/platform/entity-workspace/entity-metadata'

export type Subject = {
  id: number
  code: string
  name_en: string
  name_ar: string
  is_active: boolean
}

const columnHelper = createColumnHelper<Subject>()

/**
 * UI Sprint 1-B's third entity (docs/ADMIN_DESIGN_SYSTEM.md §28.17) --
 * the agreed checkpoint: near-identical to grade-level.tsx (code +
 * bilingual name + boolean status), proving the pattern generalizes to
 * a second Reference Data entity with zero changes to
 * entity-workspace/'s shared shells.
 */
export const subjectEntity: EntityDefinition<Subject> = {
  key: 'subjects',
  labelSingular: 'Subject',
  labelPlural: 'Subjects',
  fields: [
    { kind: 'text', name: 'code', label: 'Code' },
    { kind: 'bilingual-name', nameEnField: 'name_en', nameArField: 'name_ar', labelEn: 'Name (English)', labelAr: 'Name (Arabic)' },
  ],
  columns: [
    columnHelper.accessor('code', { header: 'Code' }),
    columnHelper.accessor('name_en', { header: 'Name (EN)' }),
    columnHelper.accessor('name_ar', { header: 'Name (AR)' }),
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
  }),
  displayName: (row) => row.name_en,
}
