import type { ServerPage } from '@/platform/data-table/types'
import { ApiError } from '@/lib/api-client'

/**
 * Dev-only fixture data. No Identity list endpoints (Roles, Branches,
 * Permissions) actually exist yet on the backend -- confirmed by
 * inspection, there is no GET /api/v1/roles or similar today -- so this
 * harness proves the DataTable/Form/Modal/Widget frameworks against a
 * realistic, Laravel-shaped in-memory dataset instead of inventing new
 * backend surface area beyond ADR-0015's agreed prerequisite slice.
 * Swapping this for a real endpoint later is a one-line change to
 * queryFn in dev-harness.tsx, nothing else.
 */
export type MockRole = { id: number; name: string; display_name_en: string; display_name_ar: string; is_active: boolean }

const roles: MockRole[] = Array.from({ length: 42 }, (_, index) => ({
  id: index + 1,
  name: `role-${index + 1}`,
  display_name_en: `Role ${index + 1}`,
  display_name_ar: `دور ${index + 1}`,
  is_active: index % 5 !== 0,
}))

export async function fetchMockRoles(params: { page: number; perPage: number; sort: string | null }): Promise<ServerPage<MockRole>> {
  await delay(200)

  const sorted = [...roles].sort((a, b) => {
    if (!params.sort) return 0
    const desc = params.sort.startsWith('-')
    const key = (desc ? params.sort.slice(1) : params.sort) as keyof MockRole
    const result = String(a[key]).localeCompare(String(b[key]))
    return desc ? -result : result
  })

  const start = (params.page - 1) * params.perPage
  const pageData = sorted.slice(start, start + params.perPage)

  return {
    data: pageData,
    meta: {
      current_page: params.page,
      last_page: Math.ceil(roles.length / params.perPage),
      per_page: params.perPage,
      total: roles.length,
    },
  }
}

export async function createMockRole(values: { name: string; display_name_en: string; display_name_ar: string }): Promise<MockRole> {
  await delay(300)

  if (roles.some((role) => role.name === values.name)) {
    throw new ApiError(422, 'The given data was invalid.', { name: ['The name has already been taken.'] })
  }

  const role: MockRole = { id: roles.length + 1, is_active: true, ...values }
  roles.unshift(role)
  return role
}

function delay(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}
