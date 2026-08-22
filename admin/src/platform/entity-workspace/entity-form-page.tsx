import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Loader2 } from 'lucide-react'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { TextField } from '@/platform/forms/text-field'
import { SelectField } from '@/platform/forms/select-field'
import { AsyncSelectField } from '@/platform/forms/async-select-field'
import { DateField } from '@/platform/forms/date-field'
import { BilingualNameField } from '@/platform/forms/bilingual-name-field'
import { mapServerErrors } from '@/platform/forms/map-server-errors'
import { useUnsavedChangesGuard } from '@/platform/forms/use-unsaved-changes-guard'
import { useEntityMutation } from '@/platform/forms/use-entity-mutation'
import { StickyActionBar } from '@/platform/components/ui/sticky-action-bar'
import { Button } from '@/platform/components/ui/button'
import type { EntityDefinition, EntityDataProvider, EntityField } from '@/platform/entity-workspace/entity-metadata'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

function buildDefaultValues<T>(fields: EntityField<T>[], existing?: T): Record<string, unknown> {
  const record = (existing ?? {}) as Record<string, unknown>
  const defaults: Record<string, unknown> = {}
  for (const field of fields) {
    if (field.kind === 'bilingual-name') {
      defaults[field.nameEnField] = record[field.nameEnField] ?? ''
      defaults[field.nameArField] = record[field.nameArField] ?? ''
    } else if (field.kind === 'date') {
      // A real backend returns a full ISO datetime for a date-cast
      // column (e.g. "2026-09-01T00:00:00.000000Z") -- HTML
      // <input type="date"> silently rejects anything but a bare
      // YYYY-MM-DD value, which would otherwise render every Edit
      // form's date field blank. Truncated once here, for every
      // entity's own date fields, not per-entity.
      defaults[field.name] = String(record[field.name] ?? '').slice(0, 10)
    } else if (field.kind === 'select' || field.kind === 'async-select') {
      // Radix Select's value prop must be a string -- a real backend FK
      // column (e.g. Term.academic_year_id) is not guaranteed to come
      // back as a string from the driver, and a raw number here would
      // silently fail to match any option's own string value, leaving
      // Edit's dropdown blank even though the row genuinely has a value.
      const raw = record[field.name]
      defaults[field.name] = raw === undefined || raw === null ? '' : String(raw)
    } else {
      defaults[field.name] = record[field.name] ?? ''
    }
  }
  return defaults
}

/**
 * The Entity Form page template (docs/ADMIN_DESIGN_SYSTEM.md §28.3/
 * §28.6) -- always a full page (§7.2's already-frozen Form Template),
 * never a modal, for every entity regardless of field count. Renders
 * fields entirely from the entity's declared field-config (§28.8 rule
 * 1) -- no per-entity branch inside this file. Consistency Invariants
 * are never duplicated here (§28.6/§28.8 rule 6): a mismatch surfaces
 * as an ordinary 422 via mapServerErrors, exactly like any other
 * server validation error.
 */
export function EntityFormPage<T extends { id: number | string }>({
  entity,
  provider,
  mode,
  id,
  onSaved,
  onCancel,
  listHref,
}: {
  entity: EntityDefinition<T>
  provider: EntityDataProvider<T>
  mode: 'create' | 'edit'
  id?: string
  onSaved: (row: T) => void
  onCancel: () => void
  listHref: string
}) {
  const { t } = useTranslation('entity-workspace')

  const existingQuery = useQuery({
    queryKey: [entity.key, 'detail', id],
    queryFn: () => provider.get(id as string),
    enabled: mode === 'edit' && id !== undefined,
  })

  // Typed as Record<string, unknown>, not a per-entity generic -- the
  // whole point of the declarative field-config (§28.8) is that this
  // shell never depends on a specific entity's shape at the type level
  // any more than it does at the render level.
  const { control, handleSubmit, setError, reset, formState } = useForm<Record<string, unknown>>({
    resolver: zodResolver(entity.schema as never),
    defaultValues: buildDefaultValues(entity.fields),
  })

  useEffect(() => {
    if (mode === 'edit' && existingQuery.data) {
      reset(buildDefaultValues(entity.fields, existingQuery.data))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [existingQuery.data])

  useUnsavedChangesGuard(formState.isDirty)

  // Deliberately not called synchronously inside onSuccess below. A
  // real bug found live during verification: calling reset() then
  // onSaved(row)'s navigate() in the same synchronous callback races
  // TanStack Router's useBlocker, whose re-registration effect (picking
  // up reset()'s now-false isDirty) only runs on React's NEXT commit --
  // one tick too late for a navigate() that already fired. The result
  // was a real, reproduced bug: a successful Save still popped the
  // "Discard unsaved changes?" prompt on its own resulting navigation.
  // Routing the row through state and firing onSaved from its own
  // effect guarantees the guard's re-registration has already
  // committed by the time navigation actually happens.
  const [savedRow, setSavedRow] = useState<T | null>(null)

  useEffect(() => {
    if (savedRow) onSaved(savedRow)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [savedRow])

  const mutation = useEntityMutation({
    mutationFn: (values: Record<string, unknown>) => (mode === 'create' ? provider.create(values) : provider.update(id as string, values)),
    successMessage: t(mode === 'create' ? 'mutation.createSuccess' : 'mutation.updateSuccess', { entity: entity.labelSingular }),
    invalidateQueryKey: [entity.key, 'list'],
    onSuccess: (row) => {
      reset(buildDefaultValues(entity.fields, row))
      setSavedRow(row)
    },
    onError: (error) => {
      mapServerErrors(error, setError as any)
    },
  })

  const title = t(mode === 'create' ? 'form.createTitle' : 'form.editTitle', { entity: entity.labelSingular })
  useBreadcrumbSegments([{ label: entity.labelPlural, href: listHref }, { label: title }])

  if (mode === 'edit' && existingQuery.isLoading) {
    return <Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />
  }

  return (
    <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="flex flex-col gap-6">
      <WorkspaceHeader title={title} />

      <div className="flex flex-col gap-4 rounded-md border p-6">
        {entity.fields.map((field, index) => {
          if (field.kind === 'bilingual-name') {
            return (
              <BilingualNameField
                key={index}
                control={control as any}
                nameEnField={field.nameEnField as any}
                nameArField={field.nameArField as any}
                labelEn={field.labelEn}
                labelAr={field.labelAr}
              />
            )
          }
          if (field.kind === 'select') {
            return <SelectField key={index} control={control as any} name={field.name as any} label={field.label} options={field.options} />
          }
          if (field.kind === 'async-select') {
            return (
              <AsyncSelectField
                key={index}
                control={control as any}
                name={field.name as any}
                label={field.label}
                loadOptions={field.loadOptions}
                queryKey={field.queryKey}
              />
            )
          }
          if (field.kind === 'date') {
            return <DateField key={index} control={control as any} name={field.name as any} label={field.label} />
          }
          return <TextField key={index} control={control as any} name={field.name as any} label={field.label} type={field.type ?? 'text'} />
        })}
      </div>

      <StickyActionBar>
        <Button type="button" variant="outline" onClick={onCancel}>
          {t('form.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('form.saveButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}
