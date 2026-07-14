import type { Control, FieldValues, Path } from 'react-hook-form'
import { TextField } from '@/platform/forms/text-field'

type Props<T extends FieldValues> = {
  control: Control<T>
  name: Path<T>
  label: string
}

export function DateField<T extends FieldValues>({ control, name, label }: Props<T>) {
  return <TextField control={control} name={name} label={label} type="date" />
}
