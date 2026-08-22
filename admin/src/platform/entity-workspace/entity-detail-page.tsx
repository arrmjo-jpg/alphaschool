import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, Loader2 } from 'lucide-react'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { Button } from '@/platform/components/ui/button'
import { EntityFieldValue } from '@/platform/entity-workspace/entity-field-value'
import type { EntityDefinition, EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The Entity Detail page template (docs/ADMIN_DESIGN_SYSTEM.md §28.3/
 * §28.5) -- an independent pattern from EntityFormPage, not a
 * disabled-form variant, closing §M8 (no read-only view distinct from
 * edit existed anywhere in Old Admin). The shell is guaranteed here;
 * entity-specific related-record panels/timelines are deliberately
 * NOT built in Sprint 1-A (§28.5 -- added only once a consuming entity
 * is actually built).
 */
export function EntityDetailPage<T extends { id: number | string }>({
  entity,
  provider,
  id,
  onEdit,
  onBack,
  backTo,
}: {
  entity: EntityDefinition<T>
  provider: EntityDataProvider<T>
  id: string
  onEdit: () => void
  onBack: () => void
  backTo: string
}) {
  const { t } = useTranslation('entity-workspace')

  const { data: row, isLoading, isError } = useQuery({
    queryKey: [entity.key, 'detail', id],
    queryFn: () => provider.get(id),
  })

  useBreadcrumbSegments([{ label: entity.labelPlural, href: backTo }, { label: row ? entity.displayName(row) : '…' }])

  if (isLoading) {
    return <Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />
  }

  if (isError || !row) {
    return <p className="p-6 text-sm text-destructive">{t('list.error.title')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <Button variant="ghost" size="sm" onClick={onBack} className="w-fit gap-1.5">
        <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
        {t('detail.backButton', { entity: entity.labelPlural })}
      </Button>

      <WorkspaceHeader
        title={entity.displayName(row)}
        actions={
          entity.capabilities.canEdit && (
            <Button onClick={onEdit}>{t('detail.editButton')}</Button>
          )
        }
      />

      <dl className="grid grid-cols-1 gap-6 rounded-md border p-6 sm:grid-cols-2">
        {entity.fields.map((field, index) => (
          <div key={index} className={field.kind === 'bilingual-name' ? 'sm:col-span-2' : undefined}>
            <EntityFieldValue field={field} row={row} />
          </div>
        ))}
      </dl>
    </div>
  )
}
