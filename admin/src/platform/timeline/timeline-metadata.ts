/**
 * The Timeline pattern's own row contract (docs/ADMIN_DESIGN_SYSTEM.md
 * §30.1/§30.3) -- a third pattern alongside §28's Entity List/Detail/
 * Form, sized for HasTemporalAssignment-backed facts. `primaryLabel` is
 * the one deliberately entity-specific slot (§30.7: "the Create form's
 * 'other party' field... Employee here; Section for SectionAssignment")
 * -- the reusable Timeline component itself never knows what a given
 * entity's anchor+subject means, so the caller resolves whichever
 * bilingual name field applies (employee_name_en/ar today) to a single
 * locale-appropriate string before rows reach this component.
 */
export type TimelineStatus = 'scheduled' | 'active' | 'ended' | 'cancelled'

export type TimelineRow = {
  id: number | string
  primaryLabel: string
  effective_from: string
  effective_until: string | null
  status: TimelineStatus
  reason_code: string | null
  ended_by_name: string | null
}

/**
 * Which row is "currently active" is computed from dates client-side,
 * never trusted from the stored `status` column alone -- reproducing
 * HasTemporalAssignment's own documented invariant
 * (docs/developer/temporal-pattern.md: "status is administrative,
 * never authoritative") on the frontend rather than re-deciding it.
 * Dates are plain ISO YYYY-MM-DD strings, so lexical comparison already
 * matches chronological order.
 */
export function isCurrentAsOfToday(row: TimelineRow, today: string = new Date().toISOString().slice(0, 10)): boolean {
  if (row.status === 'cancelled') return false
  if (row.effective_from > today) return false
  if (row.effective_until !== null && row.effective_until <= today) return false
  return true
}
