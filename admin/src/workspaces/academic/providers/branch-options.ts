import { BranchListResponseSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * A minimal options loader over the new /branches lookup endpoint --
 * not a full EntityDataProvider, since Branch has no CRUD workspace
 * here (Section, UI Sprint 1-B, is the first Academic entity with a
 * real Branch FK; a full Branch workspace is a future Identity
 * module concern, out of scope).
 */
export async function loadBranchOptions() {
  const raw = await apiFetch('/branches')
  const { data } = BranchListResponseSchema.parse(raw)
  return data.map((branch) => ({ value: String(branch.id), label: branch.name_en }))
}
