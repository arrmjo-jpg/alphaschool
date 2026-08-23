import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { ArrowLeft, Inbox, Loader2 } from 'lucide-react'
import { WorkspaceHeader } from '@/platform/shell/workspace-header'
import { useBreadcrumbSegments } from '@/platform/shell/breadcrumb-store'
import { Button } from '@/platform/components/ui/button'
import { Timeline } from '@/platform/timeline/timeline'
import type { TimelineRow } from '@/platform/timeline/timeline-metadata'
import { assignmentsLinkProps, assignmentsPathString } from '@/workspaces/assignments/assignments-route'
import { homeroomAssignmentProvider } from '@/workspaces/assignments/providers/homeroom-assignment-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'
import { HOMEROOM_REASON_CONTEXT } from '@/workspaces/assignments/homeroom/constants'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * §30.3's Timeline page for one Section anchor -- the reusable Timeline
 * component itself (platform/timeline/timeline.tsx) never fetches or
 * knows what "the other party" means; all of that is resolved here,
 * Homeroom-specific, before rows reach it.
 */
export function HomeroomTimelinePage({ sectionId }: { sectionId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const sectionQuery = useQuery({ queryKey: ['sections', 'detail', sectionId], queryFn: () => sectionProvider.get(sectionId) })
  const assignmentsQuery = useQuery({
    queryKey: ['homeroom-assignments', 'timeline', sectionId],
    queryFn: () => homeroomAssignmentProvider.listForSection(sectionId),
  })
  const reasonCodesQuery = useQuery({
    queryKey: ['reason-codes', 'options', HOMEROOM_REASON_CONTEXT, locale],
    queryFn: () => loadReasonCodeOptions(HOMEROOM_REASON_CONTEXT, locale),
  })

  const reasonCodeLabels = Object.fromEntries((reasonCodesQuery.data ?? []).map((option) => [option.value, option.label]))

  const rows: TimelineRow[] = (assignmentsQuery.data ?? []).map((assignment) => ({
    id: assignment.id,
    primaryLabel: locale === 'ar' ? assignment.employee_name_ar : assignment.employee_name_en,
    effective_from: assignment.effective_from,
    effective_until: assignment.effective_until,
    status: assignment.status,
    reason_code: assignment.reason_code,
    ended_by_name: assignment.ended_by_id === null ? null : (locale === 'ar' ? assignment.ended_by_name_ar : assignment.ended_by_name_en),
  }))

  const backTo = assignmentsPathString('homeroom')
  useBreadcrumbSegments([
    { label: t('tabs.homeroom'), href: backTo },
    { label: sectionQuery.data ? sectionQuery.data.name : '…' },
  ])

  if (assignmentsQuery.isLoading || sectionQuery.isLoading) {
    return <Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />
  }

  if (assignmentsQuery.isError || sectionQuery.isError) {
    return <p className="p-6 text-sm text-destructive">{t('homeroom.timeline.error')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <Button variant="ghost" size="sm" onClick={() => navigate(assignmentsLinkProps('homeroom'))} className="w-fit gap-1.5">
        <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
        {t('homeroom.timeline.backButton')}
      </Button>

      <WorkspaceHeader
        title={sectionQuery.data?.name ?? ''}
        description={t('homeroom.timeline.description')}
        actions={
          <Button onClick={() => navigate(assignmentsLinkProps('homeroom', sectionId, 'new'))}>{t('homeroom.timeline.assignButton')}</Button>
        }
      />

      <Timeline
        rows={rows}
        reasonCodeLabels={reasonCodeLabels}
        onClose={(row) => navigate(assignmentsLinkProps('homeroom', sectionId, row.id, 'close'))}
        onCancel={(row) => navigate(assignmentsLinkProps('homeroom', sectionId, row.id, 'cancel'))}
        emptyState={
          <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-12 text-center">
            <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
            <p className="text-sm font-medium">{t('homeroom.timeline.empty.title')}</p>
            <p className="text-sm text-muted-foreground">{t('homeroom.timeline.empty.body')}</p>
          </div>
        }
      />
    </div>
  )
}
