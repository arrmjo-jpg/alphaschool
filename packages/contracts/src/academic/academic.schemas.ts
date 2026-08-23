import { z } from 'zod'

/**
 * Wire contracts for UI Sprint 1-B (docs/ADMIN_DESIGN_SYSTEM.md §28.17)
 * -- the real Academic Master Data entities bound to the List/Detail/
 * Form pattern §28 froze. One shared paginated-list envelope, since
 * every entity's list endpoint returns Laravel's standard paginator
 * shape verbatim (no custom wrapping) -- matches
 * admin/src/platform/data-table/types.ts's `ServerPage<T>` exactly.
 */
export function paginatedSchema<T extends z.ZodTypeAny>(item: T) {
  return z.object({
    data: z.array(item),
    meta: z.object({
      current_page: z.number().int(),
      last_page: z.number().int(),
      per_page: z.number().int(),
      total: z.number().int(),
    }),
  })
}

export const AcademicYearSchema = z.object({
  id: z.number().int(),
  name_en: z.string(),
  name_ar: z.string(),
  start_date: z.string(),
  end_date: z.string(),
  status: z.enum(['upcoming', 'active', 'closed']),
})

export const AcademicYearListResponseSchema = paginatedSchema(AcademicYearSchema)

export type AcademicYear = z.infer<typeof AcademicYearSchema>

export const GradeLevelSchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name_en: z.string(),
  name_ar: z.string(),
  sequence_order: z.number().int(),
  is_active: z.boolean(),
})

export const GradeLevelListResponseSchema = paginatedSchema(GradeLevelSchema)

export type GradeLevel = z.infer<typeof GradeLevelSchema>

export const SubjectSchema = z.object({
  id: z.number().int(),
  code: z.string(),
  name_en: z.string(),
  name_ar: z.string(),
  is_active: z.boolean(),
})

export const SubjectListResponseSchema = paginatedSchema(SubjectSchema)

export type Subject = z.infer<typeof SubjectSchema>

export const TermSchema = z.object({
  id: z.number().int(),
  academic_year_id: z.number().int(),
  name_en: z.string(),
  name_ar: z.string(),
  sequence_order: z.number().int(),
  start_date: z.string(),
  end_date: z.string(),
  status: z.enum(['upcoming', 'active', 'closed']),
})

export const TermListResponseSchema = paginatedSchema(TermSchema)

export type Term = z.infer<typeof TermSchema>

export const SectionSchema = z.object({
  id: z.number().int(),
  branch_id: z.number().int(),
  academic_year_id: z.number().int(),
  grade_level_id: z.number().int(),
  name: z.string(),
  capacity: z.number().int().nullable(),
  is_active: z.boolean(),
})

export const SectionListResponseSchema = paginatedSchema(SectionSchema)

export type Section = z.infer<typeof SectionSchema>

export const BranchOptionSchema = z.object({
  id: z.number().int(),
  name_en: z.string(),
  name_ar: z.string(),
})

export const BranchListResponseSchema = z.object({ data: z.array(BranchOptionSchema) })
