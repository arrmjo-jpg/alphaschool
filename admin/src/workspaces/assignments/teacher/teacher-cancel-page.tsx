import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { CancelAssignmentForm } from '@/platform/timeline/assignment-action-form'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { teacherAssignmentProvider } from '@/workspaces/assignments/providers/teacher-assignment-provider'
import { getSubjectOffering } from '@/workspaces/assignments/providers/subject-offering-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { TEACHER_REASON_CONTEXT } from '@/workspaces/assignments/teacher/constants'

/** §32.6's Cancel full-page action, reusing CancelAssignmentForm verbatim -- only context and copy change. */
export function TeacherCancelPage({ subjectOfferingId, assignmentId }: { subjectOfferingId: string; assignmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const offeringQuery = useQuery({
    queryKey: ['subject-offerings', 'detail', subjectOfferingId],
    queryFn: () => getSubjectOffering(subjectOfferingId),
  })
  const subjectName = offeringQuery.data ? (locale === 'ar' ? offeringQuery.data.subject_name_ar : offeringQuery.data.subject_name_en) : '…'
  const goTimeline = () => navigate(assignmentsLinkProps('teacher', subjectOfferingId))

  return (
    <CancelAssignmentForm
      breadcrumb={[
        { label: t('tabs.teacher'), href: assignmentsPathString('teacher') },
        { label: subjectName, href: assignmentsPathString('teacher', subjectOfferingId) },
        { label: t('teacher.cancel.title') },
      ]}
      title={t('teacher.cancel.title')}
      loadReasonOptions={() => loadReasonCodeOptions(TEACHER_REASON_CONTEXT, locale)}
      reasonQueryKey={['reason-codes', 'options', TEACHER_REASON_CONTEXT, locale]}
      onSubmit={(values) => teacherAssignmentProvider.cancel(assignmentId, values)}
      successMessage={t('teacher.cancel.success')}
      invalidateQueryKey={['teacher-assignments', 'timeline', subjectOfferingId]}
      onDone={goTimeline}
      onCancel={goTimeline}
      confirmTitle={t('teacher.cancel.confirmTitle')}
      confirmDescription={t('teacher.cancel.confirmDescription')}
    />
  )
}
