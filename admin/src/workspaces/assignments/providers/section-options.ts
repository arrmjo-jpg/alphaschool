import { sectionProvider } from '@/workspaces/academic/providers/section-provider'

/**
 * The Homeroom landing page's anchor picker (§30.2: "Section (the
 * anchor) is already a real, bounded-size entity (§29) -- the existing
 * async-select field kind... is reused verbatim for anchor selection").
 * Reuses Academic's own sectionProvider.list() rather than a second
 * REST call shape -- Section already has a full, real
 * EntityDataProvider; no anchor type-ahead is built in this slice.
 */
export async function loadSectionOptions() {
  const page = await sectionProvider.list({ page: 1, perPage: 100, sort: 'name', search: '', status: 'active', extra: {} })
  return page.data.map((section) => ({ value: String(section.id), label: section.name }))
}
