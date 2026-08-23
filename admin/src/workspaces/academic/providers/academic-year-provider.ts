import { AcademicYearListResponseSchema, AcademicYearSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'
import type { EntityDataProvider } from '@/platform/entity-workspace/entity-metadata'
import type { AcademicYear } from '@/workspaces/academic/entities/academic-year'

/**
 * The real Phase 1-B provider for AcademicYear -- the thin REST adapter
 * over AcademicYearController (backend/app/Modules/Academic/Http/
 * Controllers/AcademicYearController.php). Every response is validated
 * through @alphaschool/contracts' Zod schemas before use (ADR-0023
 * Decision 3), matching real-configuration-provider.ts's own precedent.
 */
export const academicYearProvider: EntityDataProvider<AcademicYear> = {
  async list(params) {
    const query = new URLSearchParams({
      page: String(params.page),
      per_page: String(params.perPage),
      ...(params.sort ? { sort: params.sort } : {}),
      ...(params.search ? { search: params.search } : {}),
      ...(params.status ? { status: params.status } : {}),
    })
    const raw = await apiFetch(`/academic/academic-years?${query.toString()}`)
    return AcademicYearListResponseSchema.parse(raw)
  },
  async get(id) {
    const raw = await apiFetch(`/academic/academic-years/${id}`)
    return AcademicYearSchema.parse(raw)
  },
  async create(values) {
    const raw = await apiFetch('/academic/academic-years', { method: 'POST', body: values })
    return AcademicYearSchema.parse(raw)
  },
  async update(id, values) {
    const raw = await apiFetch(`/academic/academic-years/${id}`, { method: 'PATCH', body: values })
    return AcademicYearSchema.parse(raw)
  },
  async setStatus(id, status) {
    const raw = await apiFetch(`/academic/academic-years/${id}/status`, { method: 'PATCH', body: { status } })
    return AcademicYearSchema.parse(raw)
  },
}
