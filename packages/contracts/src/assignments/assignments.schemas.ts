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

/**
 * UI Sprint 2's SectionAssignment slice (docs/ADMIN_DESIGN_SYSTEM.md
 * §31.2) -- GET /enrollments/search and GET /enrollments/{id} share this
 * shape exactly. `academic_year_id`/`grade_level_id` are deliberately
 * raw FK ids, not names -- EnrollmentController (People) cannot
 * reference Academic's AcademicYear/GradeLevel models at all
 * (deptrac.yaml's Foundation ruleset), so the Academic-side frontend
 * resolves both ids to display labels itself, via the already-existing
 * academicYearProvider/gradeLevelProvider.
 */
export const EnrollmentSchema = z.object({
  enrollment_id: z.number().int(),
  student_name_en: z.string(),
  student_name_ar: z.string(),
  student_public_id: z.string(),
  branch_id: z.number().int(),
  branch_name_en: z.string(),
  branch_name_ar: z.string(),
  academic_year_id: z.number().int(),
  grade_level_id: z.number().int(),
  status: z.string(),
})

export const EnrollmentSearchResponseSchema = z.object({ data: z.array(EnrollmentSchema) })

export type Enrollment = z.infer<typeof EnrollmentSchema>

/** GET/PATCH /academic/section-assignments (§31.6) -- mirrors HomeroomAssignmentSchema's shape exactly, anchored on Enrollment instead of Section. */
export const SectionAssignmentSchema = z.object({
  id: z.number().int(),
  enrollment_id: z.number().int(),
  section_id: z.number().int(),
  section_name: z.string(),
  grade_level_name_en: z.string(),
  grade_level_name_ar: z.string(),
  effective_from: z.string(),
  effective_until: z.string().nullable(),
  status: z.enum(['scheduled', 'active', 'ended', 'cancelled']),
  reason_code: z.string().nullable(),
  ended_by_id: z.number().int().nullable(),
  ended_by_name_en: z.string().nullable(),
  ended_by_name_ar: z.string().nullable(),
})

export const SectionAssignmentListResponseSchema = z.object({ data: z.array(SectionAssignmentSchema) })

export type SectionAssignment = z.infer<typeof SectionAssignmentSchema>
