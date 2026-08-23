import { TeacherAssignmentListResponseSchema, TeacherAssignmentSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/** The real thin REST adapter over TeacherAssignmentController (§32.6) -- mirrors homeroom-assignment-provider.ts's own shape exactly, anchored on SubjectOffering instead of Section. */
export const teacherAssignmentProvider = {
  async listForSubjectOffering(subjectOfferingId: number | string) {
    const raw = await apiFetch(`/academic/teacher-assignments?subject_offering_id=${subjectOfferingId}`)
    return TeacherAssignmentListResponseSchema.parse(raw).data
  },
  async create(values: { subject_offering_id: number; employee_id: number; effective_from: string }) {
    const raw = await apiFetch('/academic/teacher-assignments', { method: 'POST', body: values })
    return TeacherAssignmentSchema.parse(raw)
  },
  async close(id: number | string, values: { reason_code: string; effective_until?: string }) {
    const raw = await apiFetch(`/academic/teacher-assignments/${id}/close`, { method: 'PATCH', body: values })
    return TeacherAssignmentSchema.parse(raw)
  },
  async cancel(id: number | string, values: { reason_code: string }) {
    const raw = await apiFetch(`/academic/teacher-assignments/${id}/cancel`, { method: 'PATCH', body: values })
    return TeacherAssignmentSchema.parse(raw)
  },
}
