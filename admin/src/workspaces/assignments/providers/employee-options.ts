import { EmployeeListResponseSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * A minimal options loader over the new /employees lookup endpoint
 * (§30.5) -- HomeroomAssignment's Create form is the first real
 * consumer. Locale-aware (unlike loadBranchOptions/loadAcademicYearOptions
 * in the Academic workspace, which hardcode name_en) -- a real person's
 * name, shown directly to the admin picking them, follows the app's
 * current UI language rather than always defaulting to English.
 */
export async function loadEmployeeOptions(locale: string) {
  const raw = await apiFetch('/employees')
  const { data } = EmployeeListResponseSchema.parse(raw)
  return data.map((employee) => ({ value: String(employee.id), label: locale === 'ar' ? employee.name_ar : employee.name_en }))
}
