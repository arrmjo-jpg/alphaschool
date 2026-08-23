import { SectionListResponseSchema, SectionSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import type { Section } from '@/workspaces/academic/entities/section'

/** The real thin REST adapter over SectionController, mirroring term-provider.ts's extra-filter handling. */
export const sectionProvider: EntityDataProvider<Section> = {
  async list(params) {
    const query = new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      ...(params.sort ? { sort: params.sort } : {}),
      ...(params.search ? { search: params.search } : {}),
      ...(params.status ? { status: params.status } : {}),
      ...params.extra,
    })
    const raw = await apiFetch(`/academic/sections?${query.toString()}`)
    return SectionListResponseSchema.parse(raw)
  },
  async get(id) {
    const raw = await apiFetch(`/academic/sections/${id}`)
    return SectionSchema.parse(raw)
  },
  async create(values) {
    const raw = await apiFetch('/academic/sections', { method: 'POST', body: values })
    return SectionSchema.parse(raw)
  },
  async update(id, values) {
    const raw = await apiFetch(`/academic/sections/${id}`, { method: 'PATCH', body: values })
    return SectionSchema.parse(raw)
  },
  async setStatus(id, status) {
    const raw = await apiFetch(`/academic/sections/${id}/status`, { method: 'PATCH', body: { status } })
    return SectionSchema.parse(raw)
  },
}
