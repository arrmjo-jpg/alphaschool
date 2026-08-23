import type { ColumnDef } from '@tanstack/react-table'
import type { ZodType } from 'zod'
import type { ServerPage } from '@/platform/data-table/types'

/**
 * The declarative per-entity contract UI Sprint 1's List/Detail/Form
 * pattern is driven by (docs/ADMIN_DESIGN_SYSTEM.md §28.8) -- shared
 * shells (EntityListPage/EntityDetailPage/EntityFormPage) read this
 * and never branch on entity identity, the same discipline §27.5
 * already bound Provider Registry to for vendor identity.
 */

export type EntityField<T> =
  | { kind: 'text'; name: keyof T & string; label: string; type?: 'text' | 'number' }
  | {
      kind: 'bilingual-name'
      nameEnField: keyof T & string
      nameArField: keyof T & string
      labelEn: string
      labelAr: string
    }
  | { kind: 'select'; name: keyof T & string; label: string; options: { value: string; label: string }[] }
  | { kind: 'date'; name: keyof T & string; label: string }
  | {
      kind: 'async-select'
      name: keyof T & string
      label: string
      /** e.g. the owning entity's own list() call, mapped to {value,label} -- Term's academic_year_id, first real consumer (UI Sprint 1-B). */
      loadOptions: () => Promise<{ value: string; label: string }[]>
      queryKey: unknown[]
    }

/** §28.8 rule 3's extraFilters slot, made declarative -- first real consumer is Term's academic_year_id scoping (UI Sprint 1-B). EntityListPage owns the generic state/query-key handling; only the option source is entity-specific. */
export type EntityExtraFilter = {
  key: string
  label: string
  loadOptions: () => Promise<{ value: string; label: string }[]>
  queryKey: unknown[]
}

/**
 * §28.8 rule 5 -- corrects an initial draft assumption that every
 * entity shares one boolean is_active shape. AcademicYear/Term carry a
 * real three-value status lifecycle instead (confirmed by migration),
 * so the row action label and the List filter options both come from
 * this declaration, never a hardcoded assumption.
 */
export type EntityStatusConfig =
  | {
      type: 'boolean'
      field: string
      activeLabel: string
      inactiveLabel: string
      deactivateActionLabel: string
      activateActionLabel: string
    }
  | {
      type: 'lifecycle3'
      field: string
      /** Ordered upcoming -> active -> closed, matching AcademicYear/Term's own convention. */
      values: readonly [string, string, string]
      valueLabels: Record<string, string>
      closeActionLabel: string
    }

/** §28.8 rule 9, added during review as a binding pre-freeze amendment. */
export type EntityCapabilities = {
  canCreate: boolean
  canEdit: boolean
  canDeactivate: boolean
  hasDetail: boolean
  hasBulkActions: boolean
}

export type EntityListParams = {
  page: number
  perPage: number
  sort: string | null
  search: string
  status: string | null
  /** Values keyed by EntityExtraFilter.key -- empty object for entities with none declared. */
  extra: Record<string, string>
}

/**
 * §28.7 -- deliberately has no `remove`/`delete` method at all. Reference
 * Master Data entities never expose a Delete action; the type itself
 * makes that impossible to wire, not just a documented convention.
 */
export type EntityDataProvider<T> = {
  list: (params: EntityListParams) => Promise<ServerPage<T>>
  get: (id: string) => Promise<T>
  create: (values: Record<string, unknown>) => Promise<T>
  update: (id: string, values: Record<string, unknown>) => Promise<T>
  setStatus: (id: string, status: string) => Promise<T>
}

export type EntityDefinition<T extends { id: number | string }> = {
  /** URL slug, e.g. "academic-years" -- also the Tab Switcher's tab key. */
  key: string
  labelSingular: string
  labelPlural: string
  fields: EntityField<T>[]
  columns: ColumnDef<T, any>[]
  status: EntityStatusConfig
  capabilities: EntityCapabilities
  schema: ZodType
  /** Detail/Form page titles and the breadcrumb's trailing segment. */
  displayName: (row: T) => string
  /** §28.8 rule 3 -- additive, entity-specific List filters beyond the baseline Status filter. Omitted for entities with none. */
  extraFilters?: EntityExtraFilter[]
}
