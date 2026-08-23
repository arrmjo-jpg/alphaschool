import { createColumnHelper } from '@tanstack/react-table'
import { z } from 'zod'
import { Badge } from '@/platform/components/ui/badge'
import type { EntityDefinition } from '@/platform/entity-workspace/entity-metadata'
import { academicYearProvider } from '@/workspaces/academic/providers/academic-year-provider'
import { gradeLevelProvider } from '@/workspaces/academic/providers/grade-level-provider'
import { loadBranchOptions } from '@/workspaces/academic/providers/branch-options'

export type Section = {
  id: number
  branch_id: number
  academic_year_id: number
  grade_level_id: number
  name: string
  capacity: number | null
  is_active: boolean
}

const columnHelper = createColumnHelper<Section>()

async function loadAcademicYearOptions() {
  const page = await academicYearProvider.list({ page: 1, perPage: 100, sort: '-start_date', search: '', status: null, extra: {} })
  return page.data.map((year) => ({ value: String(year.id), label: year.name_en }))
}

async function loadGradeLevelOptions() {
  const page = await gradeLevelProvider.list({ page: 1, perPage: 100, sort: 'sequence_order', search: '', status: null, extra: {} })
  return page.data.map((level) => ({ value: String(level.id), label: level.name_en }))
}

/**
 * UI Sprint 1-B's fifth and final entity (docs/ADMIN_DESIGN_SYSTEM.md
 * §28.17) -- the hardest test of the pattern's genericity: a plain
 * `text` name field (§28.6/§28.8 rule 4 already anticipated this --
 * Section is the one entity with `bilingual: false`) and three real
 * FKs via 'async-select', all expressed through declared metadata
 * with zero changes to entity-workspace/'s shared List/Detail/Form
 * shells.
 */
export const sectionEntity: EntityDefinition<Section> = {
  key: 'sections',
  labelSingular: 'Section',
  labelPlural: 'Sections',
  fields: [
    { kind: 'async-select', name: 'branch_id', label: 'Branch', loadOptions: loadBranchOptions, queryKey: ['branches', 'options'] },
    { kind: 'async-select', name: 'academic_year_id', label: 'Academic Year', loadOptions: loadAcademicYearOptions, queryKey: ['academic-years', 'options'] },
    { kind: 'async-select', name: 'grade_level_id', label: 'Grade Level', loadOptions: loadGradeLevelOptions, queryKey: ['grade-levels', 'options'] },
    { kind: 'text', name: 'name', label: 'Name' },
    { kind: 'text', name: 'capacity', label: 'Capacity', type: 'number' },
  ],
  columns: [
    columnHelper.accessor('name', { header: 'Name' }),
    columnHelper.accessor('capacity', { header: 'Capacity', cell: (info) => info.getValue() ?? '—' }),
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
    branch_id: z.string().min(1),
    academic_year_id: z.string().min(1),
    grade_level_id: z.string().min(1),
    name: z.string().min(1).max(50),
    // Sent as null when blank, never '' -- Laravel's `nullable` rule
    // only treats a real null as "not provided," not an empty string.
    capacity: z
      .string()
      .optional()
      .transform((v) => (v === undefined || v === '' ? null : Number(v)))
      .pipe(z.number().int().min(1).nullable()),
  }),
  displayName: (row) => row.name,
  extraFilters: [{ key: 'academic_year_id', label: 'Academic Year', loadOptions: loadAcademicYearOptions, queryKey: ['academic-years', 'options'] }],
}
