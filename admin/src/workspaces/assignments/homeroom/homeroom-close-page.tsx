import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { CloseAssignmentForm } from '@/platform/timeline/assignment-action-form'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { homeroomAssignmentProvider } from '@/workspaces/assignments/providers/homeroom-assignment-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'
import { HOMEROOM_REASON_CONTEXT } from '@/workspaces/assignments/homeroom/constants'

/** §30.4's Close full-page action, wiring the reusable CloseAssignmentForm to HomeroomAssignmentController's own close route. */
export function HomeroomClosePage({ sectionId, assignmentId }: { sectionId: string; assignmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const sectionQuery = useQuery({ queryKey: ['sections', 'detail', sectionId], queryFn: () => sectionProvider.get(sectionId) })
  const goTimeline = () => navigate(assignmentsLinkProps('homeroom', sectionId))

  return (
    <CloseAssignmentForm
      breadcrumb={[
        { label: t('tabs.homeroom'), href: assignmentsPathString('homeroom') },
        { label: sectionQuery.data ? sectionQuery.data.name : '…', href: assignmentsPathString('homeroom', sectionId) },
        { label: t('homeroom.close.title') },
      ]}
      title={t('homeroom.close.title')}
      loadReasonOptions={() => loadReasonCodeOptions(HOMEROOM_REASON_CONTEXT, locale)}
      reasonQueryKey={['reason-codes', 'options', HOMEROOM_REASON_CONTEXT, locale]}
      onSubmit={(values) => homeroomAssignmentProvider.close(assignmentId, values)}
      successMessage={t('homeroom.close.success')}
      invalidateQueryKey={['homeroom-assignments', 'timeline', sectionId]}
      onDone={goTimeline}
      onCancel={goTimeline}
      confirmTitle={t('homeroom.close.confirmTitle')}
      confirmDescription={t('homeroom.close.confirmDescription')}
    />
  )
}
