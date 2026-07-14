import type { Control, FieldValues, Path } from 'react-hook-form'
import { TextField } from '@/platform/forms/text-field'

type Props<T extends FieldValues> = {
  control: Control<T>
  nameEnField: Path<T>
  nameArField: Path<T>
  labelEn: string
  labelAr: string
}

/**
 * Mirrors the backend's bilingual name convention directly (Person's
 * *_en/*_ar column pairs, docs/DOMAIN_BLUEPRINT.md §1/§5) -- every
 * future workspace form touching a translatable name uses this instead
 * of hand-rolling two TextFields.
 */
export function BilingualNameField<T extends FieldValues>({ control, nameEnField, nameArField, labelEn, labelAr }: Props<T>) {
  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <TextField control={control} name={nameEnField} label={labelEn} />
      <div dir="rtl">
        <TextField control={control} name={nameArField} label={labelAr} />
      </div>
    </div>
  )
}
