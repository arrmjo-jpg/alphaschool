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
import { teacherAssignmentProvider } from '@/workspaces/assignments/providers/teacher-assignment-provider'
import { getSubjectOffering } from '@/workspaces/assignments/providers/subject-offering-provider'
import { loadReasonCodeOptions } from '@/workspaces/assignments/providers/reason-code-options'
import { TEACHER_REASON_CONTEXT } from '@/workspaces/assignments/teacher/constants'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * §32.5's Timeline page for one SubjectOffering anchor -- structurally
 * the same shape as Homeroom's own Timeline page (anchor fixed, the
 * varying "other party" per row is the assigned Employee), not
 * SectionAssignment's. Title = Subject name; description = Section ·
 * Term · Academic Year, all resolved directly from
 * SubjectOfferingController's own response (no client-side id-to-name
 * resolution needed here, unlike SectionAssignment's Enrollment).
 */
export function TeacherTimelinePage({ subjectOfferingId }: { subjectOfferingId: string }) {
  const { t, i18n } = useTranslation('assignments')
  const navigate = useNavigate()
  const locale = i18n.language

  const offeringQuery = useQuery({
    queryKey: ['subject-offerings', 'detail', subjectOfferingId],
    queryFn: () => getSubjectOffering(subjectOfferingId),
  })
  const assignmentsQuery = useQuery({
    queryKey: ['teacher-assignments', 'timeline', subjectOfferingId],
    queryFn: () => teacherAssignmentProvider.listForSubjectOffering(subjectOfferingId),
  })
  const reasonCodesQuery = useQuery({
    queryKey: ['reason-codes', 'options', TEACHER_REASON_CONTEXT, locale],
    queryFn: () => loadReasonCodeOptions(TEACHER_REASON_CONTEXT, locale),
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

  const offering = offeringQuery.data
  const subjectName = offering ? (locale === 'ar' ? offering.subject_name_ar : offering.subject_name_en) : ''
  const termName = offering ? (locale === 'ar' ? offering.term_name_ar : offering.term_name_en) : ''
  const academicYearName = offering ? (locale === 'ar' ? offering.academic_year_name_ar : offering.academic_year_name_en) : ''
  const contextLine = offering ? [offering.section_name, termName, academicYearName].filter(Boolean).join(' · ') : ''

  const backTo = assignmentsPathString('teacher')
  useBreadcrumbSegments([
    { label: t('tabs.teacher'), href: backTo },
    { label: subjectName || '…' },
  ])

  if (assignmentsQuery.isLoading || offeringQuery.isLoading) {
    return <Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />
  }

  if (assignmentsQuery.isError || offeringQuery.isError) {
    return <p className="p-6 text-sm text-destructive">{t('teacher.timeline.error')}</p>
  }

  return (
    <div className="flex flex-col gap-6">
      <Button variant="ghost" size="sm" onClick={() => navigate(assignmentsLinkProps('teacher'))} className="w-fit gap-1.5">
        <ArrowLeft className={cn(ICON_SIZE.dense, 'rtl:rotate-180')} aria-hidden="true" />
        {t('teacher.timeline.backButton')}
      </Button>

      <WorkspaceHeader
        title={subjectName}
        description={contextLine}
        actions={
          <Button onClick={() => navigate(assignmentsLinkProps('teacher', subjectOfferingId, 'new'))}>{t('teacher.timeline.assignButton')}</Button>
        }
      />

      <Timeline
        rows={rows}
        reasonCodeLabels={reasonCodeLabels}
        onClose={(row) => navigate(assignmentsLinkProps('teacher', subjectOfferingId, row.id, 'close'))}
        onCancel={(row) => navigate(assignmentsLinkProps('teacher', subjectOfferingId, row.id, 'cancel'))}
        emptyState={
          <div className="flex flex-col items-center gap-2 rounded-md border border-dashed p-12 text-center">
            <Inbox className={cn(ICON_SIZE.prominent, 'text-muted-foreground')} aria-hidden="true" />
            <p className="text-sm font-medium">{t('teacher.timeline.empty.title')}</p>
            <p className="text-sm text-muted-foreground">{t('teacher.timeline.empty.body')}</p>
          </div>
        }
      />
    </div>
  )
}
