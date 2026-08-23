import { useEffect } from 'react'
import { useNavigate } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Tabs, TabsList, TabsTrigger } from '@/platform/components/ui/tabs'
import { useWorkspaceSubPath } from '@/platform/navigation/workspace-sub-path'
import { assignmentsLinkProps } from '@/workspaces/assignments/assignments-route'
import { HomeroomLandingPage } from '@/workspaces/assignments/homeroom/homeroom-landing-page'
import { HomeroomTimelinePage } from '@/workspaces/assignments/homeroom/homeroom-timeline-page'
import { HomeroomCreatePage } from '@/workspaces/assignments/homeroom/homeroom-create-page'
import { HomeroomClosePage } from '@/workspaces/assignments/homeroom/homeroom-close-page'
import { HomeroomCancelPage } from '@/workspaces/assignments/homeroom/homeroom-cancel-page'

const TABS = ['homeroom', 'section', 'teacher'] as const
type Tab = (typeof TABS)[number]

/**
 * The Temporal Assignment Workspace (docs/ADMIN_DESIGN_SYSTEM.md §30.2)
 * -- a flat Tab Switcher mirroring §28.2's own precedent exactly, but
 * NOT built on EntityWorkspaceShell (§30.1: a genuinely different
 * pattern, no List/Detail/Form/Edit). `homeroom` is the only wired tab
 * this slice (§30.8: "SectionAssignment and TeacherAssignment are
 * explicitly deferred until this slice closes") -- `section`/`teacher`
 * render a stub panel, added for real only when their own slice is
 * built, never spun up speculatively.
 */
export default function AssignmentsWorkspacePage() {
  const { t } = useTranslation('assignments')
  const navigate = useNavigate()
  const subPath = useWorkspaceSubPath()
  const segments = subPath.split('/').filter(Boolean)
  const [activeSegment, ...rest] = segments
  const activeTab = TABS.includes(activeSegment as Tab) ? (activeSegment as Tab) : undefined

  useEffect(() => {
    if (!activeTab) {
      navigate({ ...assignmentsLinkProps('homeroom'), replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeSegment])

  if (!activeTab) return null

  return (
    <div className="flex flex-col gap-6">
      <Tabs value={activeTab} onValueChange={(tab) => navigate(assignmentsLinkProps(tab))}>
        <TabsList aria-label={t('tabs.ariaLabel')}>
          {TABS.map((tab) => (
            <TabsTrigger key={tab} value={tab}>
              {t(`tabs.${tab}`)}
            </TabsTrigger>
          ))}
        </TabsList>
      </Tabs>

      {activeTab === 'homeroom' ? <HomeroomRoutes segments={rest} /> : <StubTabPanel tab={activeTab} />}
    </div>
  )
}

function HomeroomRoutes({ segments }: { segments: string[] }) {
  if (segments.length === 0) {
    return <HomeroomLandingPage />
  }

  const [sectionId, ...rest] = segments as [string, ...string[]]

  if (rest.length === 0) {
    return <HomeroomTimelinePage sectionId={sectionId} />
  }
  if (rest.length === 1 && rest[0] === 'new') {
    return <HomeroomCreatePage sectionId={sectionId} />
  }
  if (rest.length === 2 && rest[1] === 'close') {
    return <HomeroomClosePage sectionId={sectionId} assignmentId={rest[0]!} />
  }
  if (rest.length === 2 && rest[1] === 'cancel') {
    return <HomeroomCancelPage sectionId={sectionId} assignmentId={rest[0]!} />
  }

  return null
}

function StubTabPanel({ tab }: { tab: Tab }) {
  const { t } = useTranslation('assignments')
  return <p className="p-6 text-sm text-muted-foreground">{t('tabs.comingSoon', { tab: t(`tabs.${tab}`) })}</p>
}
