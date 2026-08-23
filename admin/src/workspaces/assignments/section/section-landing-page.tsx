import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { SearchComboboxField, type SearchComboboxOption } from '@/platform/forms/search-combobox-field'
import { assignmentsLinkProps } from '@/workspaces/assignments/assignments-route'
import { searchEnrollments } from '@/workspaces/assignments/providers/enrollment-provider'
import { loadOpenAcademicYear, loadGradeLevelNameMap } from '@/workspaces/assignments/providers/academic-context'

/**
 * §31.3's Landing page -- structurally different from Homeroom's own
 * (a single bounded async-select + explicit "View" button): a live
 * search box, since the anchor here (Enrollment, reached only by
 * finding a specific student) has no small bound. Selecting a result
 * navigates immediately, matching this codebase's existing SearchBar
 * interaction (select = navigate) -- a deliberate, confirmed divergence
 * from Homeroom's own pick-then-confirm shape.
 */
export function SectionLandingPage() {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language
  const { control } = useForm<{ enrollment_id: string }>({ defaultValues: { enrollment_id: '' } })

  const openYearQuery = useQuery({ queryKey: ['academic-years', 'open'], queryFn: loadOpenAcademicYear })
  const gradeLevelsQuery = useQuery({ queryKey: ['grade-levels', 'name-map'], queryFn: loadGradeLevelNameMap })

  useBreadcrumbSegments([{ label: t('tabs.section') }])

  const openYear = openYearQuery.data
  const gradeLevelNames = gradeLevelsQuery.data ?? {}

  async function search(query: string): Promise<SearchComboboxOption[]> {
    // §31.3: the "no open Academic Year" case is deliberately not
    // distinguished from an ordinary empty-results state -- returning
    // an empty list here (rather than short-circuiting with a special
    // message) reuses the combobox's own generic "no results" copy.
    if (!openYear) return []

    const results = await searchEnrollments(query, openYear.id)

    return results.map((enrollment) => {
      const gradeLevel = gradeLevelNames[enrollment.grade_level_id]
      const gradeLevelName = gradeLevel ? (locale === 'ar' ? gradeLevel.ar : gradeLevel.en) : ''
      const branchName = locale === 'ar' ? enrollment.branch_name_ar : enrollment.branch_name_en
      const studentName = locale === 'ar' ? enrollment.student_name_ar : enrollment.student_name_en

      return {
        value: String(enrollment.enrollment_id),
        label: studentName,
        description: [gradeLevelName, branchName].filter(Boolean).join(' · '),
      }
    })
  }

  return (
    <div className="flex flex-col gap-6">
      <WorkspaceHeader title={t('section.landing.title')} description={t('section.landing.description')} />
      <div className="flex max-w-md flex-col gap-4">
        <SearchComboboxField
          control={control}
          name="enrollment_id"
          label={t('section.landing.searchLabel')}
          search={search}
          onSelect={(option) => navigate(assignmentsLinkProps('section', option.value))}
          placeholder={t('section.landing.searchPlaceholder')}
          hintMessage={t('section.landing.searchHint')}
          emptyMessage={t('section.landing.noResults')}
        />
      </div>
    </div>
  )
}
