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
import { sectionAssignmentProvider } from '@/workspaces/assignments/providers/section-assignment-provider'
import { getEnrollment } from '@/workspaces/assignments/providers/enrollment-provider'
import { loadSectionOptionsForEnrollment } from '@/workspaces/assignments/providers/section-options-for-enrollment'

const schema = z.object({
  section_id: z.string().min(1),
  effective_from: z.string().min(1),
})
type FormValues = z.infer<typeof schema>

/**
 * §31.6's Create (Assign) full-page action -- the "other party" here is
 * Section, small and bounded exactly like Homeroom's own anchor picker,
 * so it reuses the existing async-select field kind rather than
 * SearchCombobox. Extended beyond Homeroom's own unconstrained Section
 * picker: options are filtered to Sections matching this Enrollment's
 * own branch/academic_year/grade_level (§31.6/§31.7) -- the Consistency
 * Invariant itself is never re-implemented here, only made rare to hit.
 */
export function SectionCreatePage({ enrollmentId }: { enrollmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const enrollmentQuery = useQuery({ queryKey: ['enrollments', 'detail', enrollmentId], queryFn: () => getEnrollment(enrollmentId) })

  const { control, handleSubmit, setError, formState, reset } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { section_id: '', effective_from: new Date().toISOString().slice(0, 10) },
  })

  useUnsavedChangesGuard(formState.isDirty)

  const goTimeline = () => navigate(assignmentsLinkProps('section', enrollmentId))

  // Same race entity-form-page.tsx's own docblock documents and
  // homeroom-create-page.tsx already fixed: reset() and navigate() must
  // not fire synchronously in the same callback, or useBlocker's stale
  // re-registration effect still blocks the resulting navigation.
  const [created, setCreated] = useState(false)

  useEffect(() => {
    if (created) goTimeline()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [created])

  const mutation = useEntityMutation({
    mutationFn: (values: FormValues) =>
      sectionAssignmentProvider.create({
        enrollment_id: Number(enrollmentId),
        section_id: Number(values.section_id),
        effective_from: values.effective_from,
      }),
    successMessage: t('section.create.success'),
    invalidateQueryKey: ['section-assignments', 'timeline', enrollmentId],
    onSuccess: () => {
      reset()
      setCreated(true)
    },
    onError: (error) => {
      mapServerErrors(error, setError)
    },
  })

  const studentName = enrollmentQuery.data
    ? (locale === 'ar' ? enrollmentQuery.data.student_name_ar : enrollmentQuery.data.student_name_en)
    : '…'

  useBreadcrumbSegments([
    { label: t('tabs.section'), href: assignmentsPathString('section') },
    { label: studentName, href: assignmentsPathString('section', enrollmentId) },
    { label: t('section.create.title') },
  ])

  return (
    <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="flex flex-col gap-6">
      <WorkspaceHeader title={t('section.create.title')} />
      <div className="flex flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField
          control={control}
          name="section_id"
          label={t('section.create.sectionLabel')}
          loadOptions={() => (enrollmentQuery.data ? loadSectionOptionsForEnrollment(enrollmentQuery.data) : Promise.resolve([]))}
          queryKey={['sections', 'options', 'for-enrollment', enrollmentId]}
        />
        <DateField control={control} name="effective_from" label={t('section.create.effectiveFromLabel')} />
      </div>
      <StickyActionBar>
        <Button type="button" variant="outline" onClick={goTimeline}>
          {t('section.create.cancelButton')}
        </Button>
        <Button type="submit" disabled={mutation.isPending}>
          {t('section.create.submitButton')}
        </Button>
      </StickyActionBar>
    </form>
  )
}
