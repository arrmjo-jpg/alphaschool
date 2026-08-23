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
import { teacherAssignmentProvider } from '@/workspaces/assignments/providers/teacher-assignment-provider'
import { getSubjectOffering } from '@/workspaces/assignments/providers/subject-offering-provider'
import { loadEmployeeOptions } from '@/workspaces/assignments/providers/employee-options'

const schema = z.object({
  employee_id: z.string().min(1),
  effective_from: z.string().min(1),
})
type FormValues = z.infer<typeof schema>

/**
 * §32.6's Create (Assign) full-page action -- reused shape from
 * Homeroom exactly, not extended the way SectionAssignment's was:
 * Employee (async-select, reusing loadEmployeeOptions completely
 * unchanged) + effective_from, nothing else. SubjectOffering is fixed
 * by the route, matching Homeroom's own Section-fixed-by-route shape.
 * No client-side filtering of the Employee list -- no invariant links
 * Employee to SubjectOffering the way SectionAssignment's Section
 * picker needed constraining (§32.6's own disclosed simplification).
 */
export function TeacherCreatePage({ subjectOfferingId }: { subjectOfferingId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const offeringQuery = useQuery({
    queryKey: ['subject-offerings', 'detail', subjectOfferingId],
    queryFn: () => getSubjectOffering(subjectOfferingId),
  })

  const { control, handleSubmit, setError, formState, reset } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { employee_id: '', effective_from: new Date().toISOString().slice(0, 10) },
  })

  useUnsavedChangesGuard(formState.isDirty)

  const goTimeline = () => navigate(assignmentsLinkProps('teacher', subjectOfferingId))

  // Same race entity-form-page.tsx's own docblock documents and both
  // sibling Create pages already fixed: reset() and navigate() must not
  // fire synchronously in the same callback, or useBlocker's stale
  // re-registration effect still blocks the resulting navigation.
  const [created, setCreated] = useState(false)

  useEffect(() => {
    if (created) goTimeline()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [created])

  const mutation = useEntityMutation({
    mutationFn: (values: FormValues) =>
      teacherAssignmentProvider.create({
        subject_offering_id: Number(subjectOfferingId),
        employee_id: Number(values.employee_id),
        effective_from: values.effective_from,
      }),
    successMessage: t('teacher.create.success'),
    invalidateQueryKey: ['teacher-assignments', 'timeline', subjectOfferingId],
    onSuccess: () => {
      reset()
      setCreated(true)
    },
    onError: (error) => {
      mapServerErrors(error, setError)
    },
  })

  const subjectName = offeringQuery.data ? (locale === 'ar' ? offeringQuery.data.subject_name_ar : offeringQuery.data.subject_name_en) : '…'

  useBreadcrumbSegments([
    { label: t('tabs.teacher'), href: assignmentsPathString('teacher') },
    { label: subjectName, href: assignmentsPathString('teacher', subjectOfferingId) },
    { label: t('teacher.create.title') },
  ])

  return (
    <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="flex flex-col gap-6">
      <WorkspaceHeader title={t('teacher.create.title')} />
      <div className="flex flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField
          control={control}
          name="employee_id"
          label={t('teacher.create.employeeLabel')}
          loadOptions={() => loadEmployeeOptions(locale)}
          queryKey={['employees', 'options', locale]}
        />
        <DateField control={control} name="effective_from" label={t('teacher.create.effectiveFromLabel')} />
      </div>
      <StickyActionBar>
        <Button type="button" variant="outline" onClick={goTimeline}>
          {t('teacher.create.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('teacher.create.submitButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}
