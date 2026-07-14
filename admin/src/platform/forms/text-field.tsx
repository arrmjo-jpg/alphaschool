import { useController, type Control, type FieldValues, type Path } from 'react-hook-form'
import { Input } from '@/platform/components/ui/input'
import { Label } from '@/platform/components/ui/label'
import { cn } from '@/lib/cn'

type Props<T extends FieldValues> = {
  control: Control<T>
  name: Path<T>
  label: string
  type?: string
  placeholder?: string
  description?: string
}

export function TextField<T extends FieldValues>({ control, name, label, type = 'text', placeholder, description }: Props<T>) {
  const { field, fieldState } = useController({ control, name })

  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={name}>{label}</Label>
      <Input
        id={name}
        type={type}
        placeholder={placeholder}
        aria-invalid={fieldState.invalid}
        className={cn(fieldState.invalid && 'border-destructive focus-visible:ring-destructive')}
        {...field}
        value={field.value ?? ''}
      />
      {description && !fieldState.error && <p className="text-xs text-muted-foreground">{description}</p>}
      {fieldState.error && <p className="text-xs text-destructive">{fieldState.error.message}</p>}
    </div>
  )
}
