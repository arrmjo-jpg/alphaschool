import { GradeLevelListResponseSchema, GradeLevelSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import type { GradeLevel } from '@/workspaces/academic/entities/grade-level'

/** The real thin REST adapter over GradeLevelController, mirroring academic-year-provider.ts exactly. */
export const gradeLevelProvider: EntityDataProvider<GradeLevel> = {
  async list(params) {
    const query = new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      ...(params.sort ? { sort: params.sort } : {}),
      ...(params.search ? { search: params.search } : {}),
      ...(params.status ? { status: params.status } : {}),
    })
    const raw = await apiFetch(`/academic/grade-levels?${query.toString()}`)
    return GradeLevelListResponseSchema.parse(raw)
  },
  async get(id) {
    const raw = await apiFetch(`/academic/grade-levels/${id}`)
    return GradeLevelSchema.parse(raw)
  },
  async create(values) {
    const raw = await apiFetch('/academic/grade-levels', { method: 'POST', body: values })
    return GradeLevelSchema.parse(raw)
  },
  async update(id, values) {
    const raw = await apiFetch(`/academic/grade-levels/${id}`, { method: 'PATCH', body: values })
    return GradeLevelSchema.parse(raw)
  },
  async setStatus(id, status) {
    const raw = await apiFetch(`/academic/grade-levels/${id}/status`, { method: 'PATCH', body: { status } })
    return GradeLevelSchema.parse(raw)
  },
}
