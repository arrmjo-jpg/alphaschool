import { academicYearProvider } from '@/workspaces/academic/providers/academic-year-provider'
import { gradeLevelProvider } from '@/workspaces/academic/providers/grade-level-provider'

/**
 * §31.2's own resolution: "which year is open" is Academic's own
 * concern, resolved here (Academic-side frontend), never inside
 * EnrollmentController (People) -- reuses the existing
 * academicYearProvider rather than a new lookup.
 */
export async function loadOpenAcademicYear(): Promise<{ id: number; nameEn: string; nameAr: string } | null> {
  const page = await academicYearProvider.list({ page: 1, perPage: 5, sort: '-start_date', search: '', status: 'active', extra: {} })
  const year = page.data[0]
  return year ? { id: year.id, nameEn: year.name_en, nameAr: year.name_ar } : null
}

/** Enrollment search results span multiple grade levels, unlike the single open Academic Year -- a real id -> label map is needed, not a single resolved value. */
export async function loadGradeLevelNameMap(): Promise<Record<number, { en: string; ar: string }>> {
  const page = await gradeLevelProvider.list({ page: 1, perPage: 100, sort: 'sequence_order', search: '', status: null, extra: {} })
  return Object.fromEntries(page.data.map((gradeLevel) => [gradeLevel.id, { en: gradeLevel.name_en, ar: gradeLevel.name_ar }]))
}
