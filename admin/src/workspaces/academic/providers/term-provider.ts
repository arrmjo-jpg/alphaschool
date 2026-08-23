import { TermListResponseSchema, TermSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import type { Term } from '@/workspaces/academic/entities/term'

/**
 * The real thin REST adapter over TermController -- the first provider
 * to fold `extra` (§28.8 rule 3's extraFilters values) into the query
 * string, matching TermController::applyExtraFilters()'s own
 * `academic_year_id` param name exactly.
 */
export const termProvider: EntityDataProvider<Term> = {
  async list(params) {
    const query = new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      ...(params.sort ? { sort: params.sort } : {}),
      ...(params.search ? { search: params.search } : {}),
      ...(params.status ? { status: params.status } : {}),
      ...params.extra,
    })
    const raw = await apiFetch(`/academic/terms?${query.toString()}`)
    return TermListResponseSchema.parse(raw)
  },
  async get(id) {
    const raw = await apiFetch(`/academic/terms/${id}`)
    return TermSchema.parse(raw)
  },
  async create(values) {
    const raw = await apiFetch('/academic/terms', { method: 'POST', body: values })
    return TermSchema.parse(raw)
  },
  async update(id, values) {
    const raw = await apiFetch(`/academic/terms/${id}`, { method: 'PATCH', body: values })
    return TermSchema.parse(raw)
  },
  async setStatus(id, status) {
    const raw = await apiFetch(`/academic/terms/${id}/status`, { method: 'PATCH', body: { status } })
    return TermSchema.parse(raw)
  },
}
