import { SubjectListResponseSchema, SubjectSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import type { Subject } from '@/workspaces/academic/entities/subject'

/** The real thin REST adapter over SubjectController, mirroring grade-level-provider.ts exactly. */
export const subjectProvider: EntityDataProvider<Subject> = {
  async list(params) {
    const query = new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      ...(params.sort ? { sort: params.sort } : {}),
      ...(params.search ? { search: params.search } : {}),
      ...(params.status ? { status: params.status } : {}),
    })
    const raw = await apiFetch(`/academic/subjects?${query.toString()}`)
    return SubjectListResponseSchema.parse(raw)
  },
  async get(id) {
    const raw = await apiFetch(`/academic/subjects/${id}`)
    return SubjectSchema.parse(raw)
  },
  async create(values) {
    const raw = await apiFetch('/academic/subjects', { method: 'POST', body: values })
    return SubjectSchema.parse(raw)
  },
  async update(id, values) {
    const raw = await apiFetch(`/academic/subjects/${id}`, { method: 'PATCH', body: values })
    return SubjectSchema.parse(raw)
  },
  async setStatus(id, status) {
    const raw = await apiFetch(`/academic/subjects/${id}/status`, { method: 'PATCH', body: { status } })
    return SubjectSchema.parse(raw)
  },
}
