import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { createColumnHelper } from '@tanstack/react-table'
import { MoreHorizontal, Plus, Inbox, SearchX } from 'lucide-react'
import { useServerDataTable } from '@/platform/data-table/use-server-data-table'
import { DataTable } from '@/platform/data-table/data-table'
import { FilterToolbar, STATUS_FILTER_ALL, type StatusFilterOption } from '@/platform/data-table/filter-toolbar'
import { ExtraFilterControl } from '@/platform/data-table/extra-filter-control'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { Button } from '@/platform/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/platform/components/ui/dropdown-menu'
import { useConfirm } from '@/platform/modals/confirm-dialog'
import { useEntityMutation } from '@/platform/forms/use-entity-mutation'
import type { EntityDefinition, EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

function buildStatusOptions<T extends { id: number | string }>(entity: EntityDefinition<T>, t: (key: string) => string): StatusFilterOption[] {
  const base: StatusFilterOption = { value: STATUS_FILTER_ALL, label: t('list.filters.all') }
  const status = entity.status
  if (status.type === 'boolean') {
    return [base, { value: 'active', label: status.activeLabel }, { value: 'inactive', label: status.inactiveLabel }]
  }
  return [base, ...status.values.map((value) => ({ value, label: status.valueLabels[value] ?? value }))]
}

function RowActionsCell<T extends { id: number | string }>({
  row,
  entity,
  provider,
  onView,
  onEdit,
}: {
  row: T
  entity: EntityDefinition<T>
  provider: EntityDataProvider<T>
  onView: (row: T) => void
  onEdit: (row: T) => void
}) {
  const { t } = useTranslation('entity-workspace')
  const confirm = useConfirm()
  const name = entity.displayName(row)

  const statusMutation = useEntityMutation({
    mutationFn: (nextStatus: string) => provider.setStatus(String(row.id), nextStatus),
    successMessage: t('mutation.statusSuccess', { entity: entity.labelSingular }),
    invalidateQueryKey: [entity.key, 'list'],
  })

  async function handleStatusChange(nextStatus: string, confirmTitleKey: string, confirmDescriptionKey?: string) {
    const confirmed = await confirm({
      title: t(confirmTitleKey, { name }),
      description: confirmDescriptionKey ? t(confirmDescriptionKey) : undefined,
      confirmLabel: t('statusConfirm.confirm'),
      variant: confirmTitleKey === 'statusConfirm.closeTitle' || confirmTitleKey === 'statusConfirm.deactivateTitle' ? 'destructive' : 'default',
    })
    if (confirmed) statusMutation.mutate(nextStatus)
  }

  // Pre-computed rather than narrowed inline in JSX -- TS's control-flow
  // narrowing across a multi-line &&-chain inside JSX does not reliably
  // carry the discriminant through to entity.status's own sub-properties.
  const record = row as Record<string, unknown>
  let statusAction: { labelKey: string; nextStatus: string; confirmTitleKey: string; confirmDescriptionKey?: string; variant?: 'destructive' } | null = null
  if (entity.capabilities.canDeactivate) {
    if (entity.status.type === 'boolean') {
      const status = entity.status
      statusAction = record[status.field]
        ? { labelKey: 'rowActions.deactivate', nextStatus: 'inactive', confirmTitleKey: 'statusConfirm.deactivateTitle', confirmDescriptionKey: 'statusConfirm.deactivateDescription', variant: 'destructive' }
        : { labelKey: 'rowActions.activate', nextStatus: 'active', confirmTitleKey: 'statusConfirm.activateTitle' }
    } else {
      const status = entity.status
      const closedValue = status.values[2]
      if (record[status.field] !== closedValue) {
        statusAction = { labelKey: 'rowActions.close', nextStatus: closedValue, confirmTitleKey: 'statusConfirm.closeTitle', confirmDescriptionKey: 'statusConfirm.closeDescription', variant: 'destructive' }
      }
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('rowActions.menuLabel')}>
          <MoreHorizontal className={ICON_SIZE.dense} />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {entity.capabilities.hasDetail && <DropdownMenuItem onSelect={() => onView(row)}>{t('rowActions.view')}</DropdownMenuItem>}
        {entity.capabilities.canEdit && <DropdownMenuItem onSelect={() => onEdit(row)}>{t('rowActions.edit')}</DropdownMenuItem>}
        {statusAction && (
          <DropdownMenuItem onSelect={() => handleStatusChange(statusAction.nextStatus, statusAction.confirmTitleKey, statusAction.confirmDescriptionKey)}>
            {t(statusAction.labelKey)}
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

/**
 * The Entity List page template (docs/ADMIN_DESIGN_SYSTEM.md §28.4) --
 * one shared component driven entirely by an entity's declared
 * metadata (§28.8), never a per-entity branch. Row actions never
 * include Delete (§28.7, a binding domain rule): only View/Edit and
 * Deactivate/Activate (boolean-status entities) or Close
 * (lifecycle-status entities).
 */
export function EntityListPage<T extends { id: number | string }>({
  entity,
  provider,
  onView,
  onEdit,
  onCreate,
}: {
  entity: EntityDefinition<T>
  provider: EntityDataProvider<T>
  onView: (row: T) => void
  onEdit: (row: T) => void
  onCreate: () => void
}) {
  const { t } = useTranslation('entity-workspace')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState(STATUS_FILTER_ALL)
  const [extraFilterValues, setExtraFilterValues] = useState<Record<string, string>>({})

  const columnHelper = useMemo(() => createColumnHelper<T>(), [])
  const columns = useMemo(
    () => [
      ...entity.columns,
      columnHelper.display({
        id: 'actions',
        header: '',
        cell: (info) => <RowActionsCell row={info.row.original} entity={entity} provider={provider} onView={onView} onEdit={onEdit} />,
      }),
    ],
    [entity, provider, onView, onEdit, columnHelper],
  )

  const activeExtraFilters = Object.fromEntries(Object.entries(extraFilterValues).filter(([, v]) => v !== STATUS_FILTER_ALL && v !== ''))

  const { table, query, page, setPage, meta } = useServerDataTable({
    queryKey: [entity.key, 'list', search, status, activeExtraFilters],
    queryFn: (params) => provider.list({ ...params, search, status: status === STATUS_FILTER_ALL ? null : status, extra: activeExtraFilters }),
    columns,
  })

  useBreadcrumbSegments([{ label: entity.labelPlural }])

  const activeFilterCount = (status === STATUS_FILTER_ALL ? 0 : 1) + Object.keys(activeExtraFilters).length
  const hasActiveFilters = search !== '' || activeFilterCount > 0

  function handleClearAll() {
    setSearch('')
    setStatus(STATUS_FILTER_ALL)
    setExtraFilterValues({})
    setPage(1)
  }

  const emptyState = hasActiveFilters ? (
    <div className="flex flex-col items-center gap-2 py-4">
      <SearchX className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
      <p className="font-medium text-foreground">{t('list.empty.filtered.title')}</p>
      <p className="text-muted-foreground">{t('list.empty.filtered.body')}</p>
      <Button variant="outline" size="sm" onClick={handleClearAll} className="mt-1">
        {t('list.filters.clearAll')}
      </Button>
    </div>
  ) : (
    <div className="flex flex-col items-center gap-2 py-4">
      <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
      <p className="font-medium text-foreground">{t('list.empty.none.title', { entity: entity.labelPlural })}</p>
      <p className="text-muted-foreground">{t('list.empty.none.body')}</p>
      {entity.capabilities.canCreate && (
        <Button size="sm" onClick={onCreate} className="mt-1 gap-1.5">
          <Plus className="size-4" aria-hidden="true" />
          {t('list.newButton', { entity: entity.labelSingular })}
        </Button>
      )}
    </div>
  )

  return (
    <div className="flex flex-col gap-6">
      <WorkspaceHeader
        title={entity.labelPlural}
        actions={
          entity.capabilities.canCreate && (
            <Button onClick={onCreate} className="gap-1.5">
              <Plus className="size-4" aria-hidden="true" />
              {t('list.newButton', { entity: entity.labelSingular })}
            </Button>
          )
        }
      />

      <FilterToolbar
        search={search}
        onSearchChange={(value) => {
          setSearch(value)
          setPage(1)
        }}
        searchPlaceholder={t('list.searchPlaceholder', { entity: entity.labelPlural })}
        status={status}
        onStatusChange={(value) => {
          setStatus(value)
          setPage(1)
        }}
        statusOptions={buildStatusOptions(entity, t)}
        statusLabel={t('list.filters.statusLabel')}
        activeFilterCount={activeFilterCount}
        onClearAll={handleClearAll}
        extraFilters={entity.extraFilters?.map((filter) => (
          <ExtraFilterControl
            key={filter.key}
            filter={filter}
            value={extraFilterValues[filter.key] ?? STATUS_FILTER_ALL}
            allLabel={t('list.filters.all')}
            onChange={(value) => {
              setExtraFilterValues((prev) => ({ ...prev, [filter.key]: value }))
              setPage(1)
            }}
          />
        ))}
      />

      <DataTable
        table={table}
        isLoading={query.isLoading}
        isFetching={query.isFetching}
        isError={query.isError}
        onRetry={() => query.refetch()}
        page={page}
        lastPage={meta?.last_page ?? 1}
        onPageChange={setPage}
        paginationLabel={t('list.pagination.pageOf', { page, lastPage: meta?.last_page ?? 1 })}
        emptyState={emptyState}
      />
    </div>
  )
}
