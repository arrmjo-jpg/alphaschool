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
import { sectionAssignmentProvider } from '@/workspaces/assignments/providers/section-assignment-provider'
import { getEnrollment } from '@/workspaces/assignments/providers/enrollment-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { SECTION_REASON_CONTEXT } from '@/workspaces/assignments/section/constants'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * §31.5's Timeline page for one Enrollment anchor -- the mirror image
 * of Homeroom's own Timeline page: there, the anchor was Section and
 * `primaryLabel` was the assigned Employee's name; here, the anchor is
 * Enrollment (a fixed student) and `primaryLabel` is the assigned
 * Section's own display label, composed here (not in the shared
 * Timeline component, which stays entirely entity-agnostic per §30.7).
 */
export function SectionTimelinePage({ enrollmentId }: { enrollmentId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const enrollmentQuery = useQuery({ queryKey: ['enrollments', 'detail', enrollmentId], queryFn: () => getEnrollment(enrollmentId) })
  const assignmentsQuery = useQuery({
    queryKey: ['section-assignments', 'timeline', enrollmentId],
    queryFn: () => sectionAssignmentProvider.listForEnrollment(enrollmentId),
  })
  const reasonCodesQuery = useQuery({
    queryKey: ['reason-codes', 'options', SECTION_REASON_CONTEXT, locale],
    queryFn: () => loadReasonCodeOptions(SECTION_REASON_CONTEXT, locale),
  })

  const reasonCodeLabels = Object.fromEntries((reasonCodesQuery.data ?? []).map((option) => [option.value, option.label]))

  const rows: TimelineRow[] = (assignmentsQuery.data ?? []).map((assignment) => {
    const gradeLevelName = locale === 'ar' ? assignment.grade_level_name_ar : assignment.grade_level_name_en

    return {
      id: assignment.id,
      primaryLabel: `${gradeLevelName} – ${assignment.section_name}`,
      effective_from: assignment.effective_from,
      effective_until: assignment.effective_until,
      status: assignment.status,
      reason_code: assignment.reason_code,
      ended_by_name: assignment.ended_by_id === null ? null : (locale === 'ar' ? assignment.ended_by_name_ar : assignment.ended_by_name_en),
    }
  })

  const studentName = enrollmentQuery.data ? (locale === 'ar' ? enrollmentQuery.data.student_name_ar : enrollmentQuery.data.student_name_en) : ''
  const backTo = assignmentsPathString('section')
  useBreadcrumbSegments([
    { label: t('tabs.section'), href: backTo },
    { label: studentName || '…' },
  ])

  if (assignmentsQuery.isLoading || enrollmentQuery.isLoading) {
    return <Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />
  }

  if (assignmentsQuery.isError || enrollmentQuery.isError) {
    return <p className="p-6 text-sm text-destructive">{t('section.timeline.error')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <Button variant="ghost" size="sm" onClick={() => navigate(assignmentsLinkProps('section'))} className="w-fit gap-1.5">
        <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
        {t('section.timeline.backButton')}
      </Button>

      <WorkspaceHeader
        title={studentName}
        description={t('section.timeline.description')}
        actions={
          <Button onClick={() => navigate(assignmentsLinkProps('section', enrollmentId, 'new'))}>{t('section.timeline.assignButton')}</Button>
        }
      />

      <Timeline
        rows={rows}
        reasonCodeLabels={reasonCodeLabels}
        onClose={(row) => navigate(assignmentsLinkProps('section', enrollmentId, row.id, 'close'))}
        onCancel={(row) => navigate(assignmentsLinkProps('section', enrollmentId, row.id, 'cancel'))}
        emptyState={
          <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-12 text-center">
            <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
            <p className="text-sm font-medium">{t('section.timeline.empty.title')}</p>
            <p className="text-sm text-muted-foreground">{t('section.timeline.empty.body')}</p>
          </div>
        }
      />
    </div>
  )
}
