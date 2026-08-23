import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { AsyncSelectField } from '@/platform/forms/async-select-field'
import { DateField } from '@/platform/forms/date-field'
import { mapServerErrors } from '@/platform/forms/map-server-errors'
import { useUnsavedChangesGuard } from '@/platform/forms/use-unsaved-changes-guard'
import { useEntityMutation } from '@/platform/forms/use-entity-mutation'
import { StickyActionBar } from '@/platform/components/ui/sticky-action-bar'
import { Button } from '@/platform/components/ui/button'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { homeroomAssignmentProvider } from '@/workspaces/assignments/providers/homeroom-assignment-provider'
import { loadEmployeeOptions } from '@/workspaces/assignments/providers/employee-options'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'

const schema = z.object({
  employee_id: z.string().min(1),
  effective_from: z.string().min(1),
})
type FormValues = z.infer<typeof schema>

/**
 * §30.4's Create (Assign) full-page action -- an async-select Employee
 * picker + effective_from, nothing else; status is never a form field,
 * it's always computed. A 422 from guardAgainstOverlap()/a closed
 * Academic Year surfaces as an inline error under Employee via the
 * existing mapServerErrors (HomeroomAssignmentController attaches both
 * to `employee_id`, the nearest real field -- Section is fixed by the
 * route) -- no overlap logic is duplicated here.
 */
export function HomeroomCreatePage({ sectionId }: { sectionId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const sectionQuery = useQuery({ queryKey: ['sections', 'detail', sectionId], queryFn: () => sectionProvider.get(sectionId) })

  const { control, handleSubmit, setError, formState, reset } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { employee_id: '', effective_from: new Date().toISOString().slice(0, 10) },
  })

  useUnsavedChangesGuard(formState.isDirty)

  const goTimeline = () => navigate(assignmentsLinkProps('homeroom', sectionId))

  // Deliberately not navigating synchronously inside onSuccess below --
  // the same real, reproduced race entity-form-page.tsx's own docblock
  // documents: reset() clearing isDirty and navigate() firing in the
  // same synchronous callback races useBlocker's re-registration effect,
  // which only picks up the now-false isDirty on React's NEXT commit.
  // Routing through state and firing navigation from its own effect
  // guarantees the guard has already re-registered by the time it runs.
  const [created, setCreated] = useState(false)

  useEffect(() => {
    if (created) goTimeline()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [created])

  const mutation = useEntityMutation({
    mutationFn: (values: FormValues) =>
      homeroomAssignmentProvider.create({
        section_id: Number(sectionId),
        employee_id: Number(values.employee_id),
        effective_from: values.effective_from,
      }),
    successMessage: t('homeroom.create.success'),
    invalidateQueryKey: ['homeroom-assignments', 'timeline', sectionId],
    onSuccess: () => {
      reset()
      setCreated(true)
    },
    onError: (error) => {
      mapServerErrors(error, setError)
    },
  })

  useBreadcrumbSegments([
    { label: t('tabs.homeroom'), href: assignmentsPathString('homeroom') },
    { label: sectionQuery.data ? sectionQuery.data.name : '…', href: assignmentsPathString('homeroom', sectionId) },
    { label: t('homeroom.create.title') },
  ])

  return (
    <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="flex flex-col gap-6">
      <WorkspaceHeader title={t('homeroom.create.title')} />
      <div className="flex flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField
          control={control}
          name="employee_id"
          label={t('homeroom.create.employeeLabel')}
          loadOptions={() => loadEmployeeOptions(locale)}
          queryKey={['employees', 'options', locale]}
        />
        <DateField control={control} name="effective_from" label={t('homeroom.create.effectiveFromLabel')} />
      </div>
      <StickyActionBar>
        <Button type="button" variant="outline" onClick={goTimeline}>
          {t('homeroom.create.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('homeroom.create.submitButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}
