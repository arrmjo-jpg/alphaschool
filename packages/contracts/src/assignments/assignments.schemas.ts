import { z } from 'zod'

/**
 * Wire contracts for UI Sprint 2's Temporal Assignment Workspace
 * (docs/ADMIN_DESIGN_SYSTEM.md §30), HomeroomAssignment vertical slice.
 * No paginated envelope here -- unlike §28's Reference Master Data
 * List, a Timeline is read as a whole for one anchor (§30.3), never
 * paged, so the response is a plain `{ data: [...] }` array, matching
 * the same shape already used for the Branch/Employee/ReasonCode
 * option-list endpoints.
 */
export const HomeroomAssignmentSchema = z.object({
  id: z.number().int(),
  section_id: z.number().int(),
  employee_id: z.number().int(),
  employee_name_en: z.string(),
  employee_name_ar: z.string(),
  effective_from: z.string(),
  effective_until: z.string().nullable(),
  status: z.enum(['scheduled', 'active', 'ended', 'cancelled']),
  reason_code: z.string().nullable(),
  ended_by_id: z.number().int().nullable(),
  ended_by_name_en: z.string().nullable(),
  ended_by_name_ar: z.string().nullable(),
})

export const HomeroomAssignmentListResponseSchema = z.object({ data: z.array(HomeroomAssignmentSchema) })

export type HomeroomAssignment = z.infer<typeof HomeroomAssignmentSchema>

/** GET /employees (§30.5) -- filtered to active employees, name_* resolved server-side via Person. */
export const EmployeeOptionSchema = z.object({
  id: z.number().int(),
  name_en: z.string(),
  name_ar: z.string(),
})

export const EmployeeListResponseSchema = z.object({ data: z.array(EmployeeOptionSchema) })

export type EmployeeOption = z.infer<typeof EmployeeOptionSchema>

/** GET /reason-codes?context=... (§30.5) -- generic across all three temporal entities, not Homeroom-specific. */
export const ReasonCodeOptionSchema = z.object({
  code: z.string(),
  label_en: z.string(),
  label_ar: z.string(),
})

export const ReasonCodeListResponseSchema = z.object({ data: z.array(ReasonCodeOptionSchema) })

export type ReasonCodeOption = z.infer<typeof ReasonCodeOptionSchema>
