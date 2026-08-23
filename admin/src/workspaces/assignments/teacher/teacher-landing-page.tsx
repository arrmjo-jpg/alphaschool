import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { AsyncSelectField } from '@/platform/forms/async-select-field'
import { Button } from '@/platform/components/ui/button'
import { assignmentsLinkProps } from '@/workspaces/assignments/assignments-route'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'
import { termProvider } from '@/workspaces/academic/providers/term-provider'
import { loadOpenAcademicYear } from '@/workspaces/assignments/providers/academic-context'
import { listSubjectOfferings } from '@/workspaces/assignments/providers/subject-offering-provider'

type FormValues = { section_id: string; term_id: string; subject_offering_id: string }

/**
 * §32.2's Landing page -- three cascading `async-select` pickers
 * (Section → Term → Subject Offering), not `SearchCombobox`: confirmed
 * during the architecture pass that SubjectOffering's own scale is
 * bounded and composite, not genuinely unbounded like Enrollment's own
 * student search (§31.3). The third picker's options are derived from
 * real SubjectOfferings only (§32.3's own filtered lookup), never the
 * raw Subject catalog -- its own `value` is the `subject_offering_id`
 * itself, so selecting it *is* the final anchor selection, with no
 * separate resolution step. An explicit "View" button, matching
 * Homeroom's own landing page, not SectionAssignment's select-and-
 * navigate search result.
 */
export function TeacherLandingPage() {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language
  const { control, handleSubmit, watch, setValue } = useForm<FormValues>({
    defaultValues: { section_id: '', term_id: '', subject_offering_id: '' },
  })

  const sectionId = watch('section_id')
  const termId = watch('term_id')
  const subjectOfferingId = watch('subject_offering_id')

  const openYearQuery = useQuery({ queryKey: ['academic-years', 'open'], queryFn: loadOpenAcademicYear })
  const openYear = openYearQuery.data

  useBreadcrumbSegments([{ label: t('tabs.teacher') }])

  // Section/Term changing invalidates whatever Subject Offering was
  // previously picked -- reset it rather than let a stale
  // subject_offering_id linger in form state for a combination it no
  // longer belongs to.
  useEffect(() => {
    setValue('subject_offering_id', '')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sectionId, termId])

  async function loadSectionOptions() {
    if (!openYear) return []
    const page = await sectionProvider.list({
      page: 1,
      perPage: 100,
      sort: 'name',
      search: '',
      status: 'active',
      extra: { academic_year_id: String(openYear.id) },
    })
    return page.data.map((section) => ({ value: String(section.id), label: section.name }))
  }

  async function loadTermOptions() {
    if (!openYear) return []
    const page = await termProvider.list({
      page: 1,
      perPage: 100,
      sort: 'sequence_order',
      search: '',
      status: null,
      extra: { academic_year_id: String(openYear.id) },
    })
    return page.data.map((term) => ({ value: String(term.id), label: locale === 'ar' ? term.name_ar : term.name_en }))
  }

  async function loadSubjectOfferingOptions() {
    if (!sectionId || !termId) return []
    const offerings = await listSubjectOfferings({ sectionId: Number(sectionId), termId: Number(termId) })
    return offerings.map((offering) => ({
      value: String(offering.id),
      label: locale === 'ar' ? offering.subject_name_ar : offering.subject_name_en,
    }))
  }

  // Shares its cache with AsyncSelectField's own internal query below
  // (identical queryKey) -- read here only to distinguish "no real
  // offering exists for this pair" from an ordinary loading state,
  // without duplicating the fetch or modifying AsyncSelectField itself.
  const subjectOfferingOptionsQuery = useQuery({
    queryKey: ['subject-offerings', 'options', sectionId, termId],
    queryFn: loadSubjectOfferingOptions,
    enabled: Boolean(sectionId && termId),
  })
  const noOfferingsForPair = Boolean(sectionId && termId) && subjectOfferingOptionsQuery.data?.length === 0

  const submit = handleSubmit((values) => {
    navigate(assignmentsLinkProps('teacher', values.subject_offering_id))
  })

  return (
    <form onSubmit={submit} className="flex flex-col gap-6">
      <WorkspaceHeader title={t('teacher.landing.title')} description={t('teacher.landing.description')} />
      <div className="flex max-w-md flex-col gap-4 rounded-md border p-6">
        <AsyncSelectField
          control={control}
          name="section_id"
          label={t('teacher.landing.sectionLabel')}
          loadOptions={loadSectionOptions}
          queryKey={['sections', 'options', 'open-year', openYear?.id]}
        />
        <AsyncSelectField
          control={control}
          name="term_id"
          label={t('teacher.landing.termLabel')}
          loadOptions={loadTermOptions}
          queryKey={['terms', 'options', 'open-year', openYear?.id]}
        />
        <AsyncSelectField
          control={control}
          name="subject_offering_id"
          label={t('teacher.landing.subjectLabel')}
          loadOptions={loadSubjectOfferingOptions}
          queryKey={['subject-offerings', 'options', sectionId, termId]}
        />
        {(!sectionId || !termId) && <p className="text-xs text-muted-foreground">{t('teacher.landing.subjectHintIncomplete')}</p>}
        {noOfferingsForPair && <p className="text-xs text-muted-foreground">{t('teacher.landing.subjectHintEmpty')}</p>}
        <Button type="submit" disabled={!subjectOfferingId} className="w-fit">
          {t('teacher.landing.viewButton')}
        </Button>
      </div>
    </form>
  )
}
