import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { CancelAssignmentForm } from '@/platform/timeline/assignment-action-form'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { sectionAssignmentProvider } from '@/workspaces/assignments/providers/section-assignment-provider'
import { getEnrollment } from '@/workspaces/assignments/providers/enrollment-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { SECTION_REASON_CONTEXT } from '@/workspaces/assignments/section/constants'

/** §31.6's Cancel full-page action, reusing CancelAssignmentForm verbatim -- only context and copy change. */
export function SectionCancelPage({ enrollmentId, assignmentId }: { enrollmentId: string; assignmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const enrollmentQuery = useQuery({ queryKey: ['enrollments', 'detail', enrollmentId], queryFn: () => getEnrollment(enrollmentId) })
  const studentName = enrollmentQuery.data ? (locale === 'ar' ? enrollmentQuery.data.student_name_ar : enrollmentQuery.data.student_name_en) : '…'
  const goTimeline = () => navigate(assignmentsLinkProps('section', enrollmentId))

  return (
    <CancelAssignmentForm
      breadcrumb={[
        { label: t('tabs.section'), href: assignmentsPathString('section') },
        { label: studentName, href: assignmentsPathString('section', enrollmentId) },
        { label: t('section.cancel.title') },
      ]}
      title={t('section.cancel.title')}
      loadReasonOptions={() => loadReasonCodeOptions(SECTION_REASON_CONTEXT, locale)}
      reasonQueryKey={['reason-codes', 'options', SECTION_REASON_CONTEXT, locale]}
      onSubmit={(values) => sectionAssignmentProvider.cancel(assignmentId, values)}
      successMessage={t('section.cancel.success')}
      invalidateQueryKey={['section-assignments', 'timeline', enrollmentId]}
      onDone={goTimeline}
      onCancel={goTimeline}
      confirmTitle={t('section.cancel.confirmTitle')}
      confirmDescription={t('section.cancel.confirmDescription')}
    />
  )
}
