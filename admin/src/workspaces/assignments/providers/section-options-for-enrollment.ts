import { sectionProvider } from '@/workspaces/academic/providers/section-provider'
import type { Enrollment } from '@alphaschool/contracts'

/**
 * §31.6's own extension over Homeroom's plain, unconstrained Section
 * picker: Section stays small enough that no new backend query is
 * needed, but the options are filtered client-side to only the Sections
 * whose branch/academic_year/grade_level all match the target
 * Enrollment's own three values -- SectionAssignment's own Consistency
 * Invariant would reject any other combination anyway (§31.7), so this
 * makes the invalid case rare rather than only catching it after a
 * wasted round-trip.
 */
export async function loadSectionOptionsForEnrollment(enrollment: Enrollment) {
  const page = await sectionProvider.list({ page: 1, perPage: 100, sort: 'name', search: '', status: 'active', extra: {} })

  return page.data
    .filter(
      (section) =>
        section.branch_id === enrollment.branch_id &&
        section.academic_year_id === enrollment.academic_year_id &&
        section.grade_level_id === enrollment.grade_level_id,
    )
    .map((section) => ({ value: String(section.id), label: section.name }))
}
