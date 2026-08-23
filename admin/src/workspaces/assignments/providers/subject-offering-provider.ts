import { SubjectOfferingListResponseSchema, SubjectOfferingSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * The thin REST adapter over SubjectOfferingController (§32.3) --
 * powers the Landing page's third cascading picker (`list()` filtered
 * by `section_id`/`term_id`, deriving only real offerings) and the
 * Timeline/Create/Close/Cancel pages' own anchor-context loading
 * (`get()`).
 */
export async function listSubjectOfferings(filters: { sectionId?: number; termId?: number; subjectId?: number } = {}) {
  const params = new URLSearchParams()
  if (filters.sectionId !== undefined) params.set('section_id', String(filters.sectionId))
  if (filters.termId !== undefined) params.set('term_id', String(filters.termId))
  if (filters.subjectId !== undefined) params.set('subject_id', String(filters.subjectId))

  const raw = await apiFetch(`/academic/subject-offerings?${params.toString()}`)
  return SubjectOfferingListResponseSchema.parse(raw).data
}

export async function getSubjectOffering(id: number | string) {
  const raw = await apiFetch(`/academic/subject-offerings/${id}`)
  return SubjectOfferingSchema.parse(raw)
}
