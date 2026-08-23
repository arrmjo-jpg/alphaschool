import { SectionAssignmentListResponseSchema, SectionAssignmentSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/** The real thin REST adapter over SectionAssignmentController (§31.6) -- mirrors homeroom-assignment-provider.ts's own shape exactly, anchored on Enrollment instead of Section. */
export const sectionAssignmentProvider = {
  async listForEnrollment(enrollmentId: number | string) {
    const raw = await apiFetch(`/academic/section-assignments?enrollment_id=${enrollmentId}`)
    return SectionAssignmentListResponseSchema.parse(raw).data
  },
  async create(values: { enrollment_id: number; section_id: number; effective_from: string }) {
    const raw = await apiFetch('/academic/section-assignments', { method: 'POST', body: values })
    return SectionAssignmentSchema.parse(raw)
  },
  async close(id: number | string, values: { reason_code: string; effective_until?: string }) {
    const raw = await apiFetch(`/academic/section-assignments/${id}/close`, { method: 'PATCH', body: values })
    return SectionAssignmentSchema.parse(raw)
  },
  async cancel(id: number | string, values: { reason_code: string }) {
    const raw = await apiFetch(`/academic/section-assignments/${id}/cancel`, { method: 'PATCH', body: values })
    return SectionAssignmentSchema.parse(raw)
  },
}
