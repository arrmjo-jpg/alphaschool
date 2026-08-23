import { HomeroomAssignmentListResponseSchema, HomeroomAssignmentSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * The real thin REST adapter over HomeroomAssignmentController
 * (docs/ADMIN_DESIGN_SYSTEM.md §30.6) -- not an EntityDataProvider
 * (§28.7's shape has no meaning here: no update/delete, and
 * close/cancel are distinct reason-and-date-carrying operations, not a
 * generic setStatus).
 */
export const homeroomAssignmentProvider = {
  async listForSection(sectionId: number | string) {
    const raw = await apiFetch(`/academic/homeroom-assignments?section_id=${sectionId}`)
    return HomeroomAssignmentListResponseSchema.parse(raw).data
  },
  async create(values: { section_id: number; employee_id: number; effective_from: string }) {
    const raw = await apiFetch('/academic/homeroom-assignments', { method: 'POST', body: values })
    return HomeroomAssignmentSchema.parse(raw)
  },
  async close(id: number | string, values: { reason_code: string; effective_until?: string }) {
    const raw = await apiFetch(`/academic/homeroom-assignments/${id}/close`, { method: 'PATCH', body: values })
    return HomeroomAssignmentSchema.parse(raw)
  },
  async cancel(id: number | string, values: { reason_code: string }) {
    const raw = await apiFetch(`/academic/homeroom-assignments/${id}/cancel`, { method: 'PATCH', body: values })
    return HomeroomAssignmentSchema.parse(raw)
  },
}
