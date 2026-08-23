import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { CancelAssignmentForm } from '@/platform/timeline/assignment-action-form'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { homeroomAssignmentProvider } from '@/workspaces/assignments/providers/homeroom-assignment-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'
import { HOMEROOM_REASON_CONTEXT } from '@/workspaces/assignments/homeroom/constants'

/** §30.4's Cancel full-page action, wiring the reusable CancelAssignmentForm to HomeroomAssignmentController's own cancel route. */
export function HomeroomCancelPage({ sectionId, assignmentId }: { sectionId: string; assignmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const sectionQuery = useQuery({ queryKey: ['sections', 'detail', sectionId], queryFn: () => sectionProvider.get(sectionId) })
  const goTimeline = () => navigate(assignmentsLinkProps('homeroom', sectionId))

  return (
    <CancelAssignmentForm
      breadcrumb={[
        { label: t('tabs.homeroom'), href: assignmentsPathString('homeroom') },
        { label: sectionQuery.data ? sectionQuery.data.name : '…', href: assignmentsPathString('homeroom', sectionId) },
        { label: t('homeroom.cancel.title') },
      ]}
      title={t('homeroom.cancel.title')}
      loadReasonOptions={() => loadReasonCodeOptions(HOMEROOM_REASON_CONTEXT, locale)}
      reasonQueryKey={['reason-codes', 'options', HOMEROOM_REASON_CONTEXT, locale]}
      onSubmit={(values) => homeroomAssignmentProvider.cancel(assignmentId, values)}
      successMessage={t('homeroom.cancel.success')}
      invalidateQueryKey={['homeroom-assignments', 'timeline', sectionId]}
      onDone={goTimeline}
      onCancel={goTimeline}
      confirmTitle={t('homeroom.cancel.confirmTitle')}
      confirmDescription={t('homeroom.cancel.confirmDescription')}
    />
  )
}
