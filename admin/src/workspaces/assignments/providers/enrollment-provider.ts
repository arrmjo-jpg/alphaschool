import { EnrollmentSchema, EnrollmentSearchResponseSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * The thin REST adapter over EnrollmentController (§31.2) -- lives
 * outside `search-combobox-field.tsx` on purpose (that component knows
 * nothing about Enrollment), the same separation `homeroom-assignment-
 * provider.ts` keeps from `platform/timeline/`.
 */
export async function searchEnrollments(query: string, academicYearId: number) {
  const params = new URLSearchParams({ q: query, academic_year_id: String(academicYearId) })
  const raw = await apiFetch(`/enrollments/search?${params.toString()}`)
  return EnrollmentSearchResponseSchema.parse(raw).data
}

export async function getEnrollment(id: number | string) {
  const raw = await apiFetch(`/enrollments/${id}`)
  return EnrollmentSchema.parse(raw)
}
