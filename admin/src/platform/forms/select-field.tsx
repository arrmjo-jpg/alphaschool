import { useController, type Control, type FieldValues, type Path } from 'react-hook-form'
import { Label } from '@/platform/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/platform/components/ui/select'

type Option = { value: string; label: string }

type Props<T extends FieldValues> = {
  control: Control<T>
  name: Path<T>
  label: string
  options: Option[]
  placeholder?: string
}

export function SelectField<T extends FieldValues>({ control, name, label, options, placeholder }: Props<T>) {
  const { field, fieldState } = useController({ control, name })

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={name}>{label}</Label>
      <Select value={field.value ?? ''} onValueChange={field.onChange}>
        <SelectTrigger id={name} aria-invalid={fieldState.invalid}>
          <SelectValue placeholder={placeholder} />
        </SelectTrigger>
        <SelectContent>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      {fieldState.error && <p className="text-xs text-destructive">{fieldState.error.message}</p>}
    </div>
  )
}
