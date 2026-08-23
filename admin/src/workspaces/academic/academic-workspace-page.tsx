import { EntityWorkspaceShell } from '@/platform/entity-workspace/entity-workspace-shell'
import { academicYearEntity } from '@/workspaces/academic/entities/academic-year'
import { academicYearProvider } from '@/workspaces/academic/providers/academic-year-provider'
import { gradeLevelEntity } from '@/workspaces/academic/entities/grade-level'
import { gradeLevelProvider } from '@/workspaces/academic/providers/grade-level-provider'
import { subjectEntity } from '@/workspaces/academic/entities/subject'
import { subjectProvider } from '@/workspaces/academic/providers/subject-provider'
import { termEntity } from '@/workspaces/academic/entities/term'
import { termProvider } from '@/workspaces/academic/providers/term-provider'
import { sectionEntity } from '@/workspaces/academic/entities/section'
import { sectionProvider } from '@/workspaces/academic/providers/section-provider'

/**
 * The real Academic workspace (docs/ADMIN_DESIGN_SYSTEM.md §28.2's
 * Flat Tab Switcher, §28.17's UI Sprint 1-B) -- entities are added to
 * `entities`/`providers` one at a time as each is wired and verified
 * (Entity Workspace Integration order: AcademicYear -> GradeLevel ->
 * Subject -> Term -> Section), never all at once.
 */
export default function AcademicWorkspacePage() {
  return (
    <EntityWorkspaceShell
      workspaceKey="academic"
      entities={[academicYearEntity, gradeLevelEntity, subjectEntity, termEntity, sectionEntity]}
      providers={{
        [academicYearEntity.key]: academicYearProvider,
        [gradeLevelEntity.key]: gradeLevelProvider,
        [subjectEntity.key]: subjectProvider,
        [termEntity.key]: termProvider,
        [sectionEntity.key]: sectionProvider,
      }}
    />
  )
}
