import { ReasonCodeListResponseSchema } from '@alphaschool/contracts'
import { apiFetch } from '@/lib/api-client'

/**
 * A minimal options loader over the generic /reason-codes lookup
 * endpoint (§30.5) -- `context` is the only thing that varies across
 * HomeroomAssignment/SectionAssignment/TeacherAssignment, so this one
 * loader is reused verbatim by every later slice, not rebuilt per
 * entity. Locale-aware for the same reason as loadEmployeeOptions --
 * the real bug the backend's own ReasonCodeController fix (§30.5's
 * docblock) guarded against was Arabic-UI users always seeing English
 * reason labels; resolving the display language here is the other half
 * of that fix.
 */
export async function loadReasonCodeOptions(context: string, locale: string) {
  const raw = await apiFetch(`/reason-codes?context=${encodeURIComponent(context)}`)
  const { data } = ReasonCodeListResponseSchema.parse(raw)
  return data.map((reasonCode) => ({
    value: reasonCode.code,
    label: locale === 'ar' ? reasonCode.label_ar : reasonCode.label_en,
  }))
}
