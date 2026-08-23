import { useEffect, useRef, useState } from 'react'
import { useController, type Control, type FieldValues, type Path } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Label } from '@/platform/components/ui/label'
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/platform/components/ui/command'
import { cn } from '@/lib/cn'

export type SearchComboboxOption = { value: string; label: string; description?: string }

/**
 * A new reusable platform primitive (docs/ADMIN_DESIGN_SYSTEM.md §31.8)
 * -- unlike `AsyncSelectField`, which preloads its full option set once,
 * this fires a live, debounced, server-side query as the user types.
 * Built for SectionAssignment's Landing page (searching a genuinely
 * unbounded population of students), but knows nothing about Enrollment,
 * Student, or any other domain concept -- the caller supplies `search()`
 * and consumes the selected `value`, the same boundary discipline
 * `AsyncSelectField` already follows. Lives in `platform/forms/`, not
 * `workspaces/assignments/`, specifically so `TeacherAssignment`'s own
 * `SubjectOffering` search can reuse it later without duplication.
 */
export function SearchComboboxField<T extends FieldValues>({
  control,
  name,
  label,
  search,
  onSelect,
  minQueryLength = 2,
  debounceMs = 250,
  placeholder,
  hintMessage,
  emptyMessage,
}: {
  control: Control<T>
  name: Path<T>
  label: string
  search: (query: string) => Promise<SearchComboboxOption[]>
  /** Fired with the full selected option, in addition to the usual field.onChange(value) -- lets a caller (e.g. a Landing page) act on selection immediately, not just store it in form state. */
  onSelect?: (option: SearchComboboxOption) => void
  minQueryLength?: number
  /** default 250, matching the existing SearchBar's own precedent */
  debounceMs?: number
  placeholder?: string
  /** Shown before minQueryLength characters are typed. */
  hintMessage?: string
  emptyMessage?: string
}) {
  const { field, fieldState } = useController({ control, name })
  const [query, setQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [selectedLabel, setSelectedLabel] = useState('')
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedQuery(query), debounceMs)
    return () => clearTimeout(timeout)
  }, [query, debounceMs])

  const enabled = debouncedQuery.trim().length >= minQueryLength

  const { data: options = [], isFetching } = useQuery({
    queryKey: ['search-combobox', name, debouncedQuery],
    queryFn: () => search(debouncedQuery),
    enabled,
  })

  useEffect(() => {
    function onClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [])

  const showPanel = open && query.trim().length > 0

  return (
    <div ref={containerRef} className="relative flex flex-col gap-1.5">
      <Label htmlFor={name}>{label}</Label>
      <Command shouldFilter={false} className="relative">
        <CommandInput
          id={name}
          value={field.value ? selectedLabel : query}
          onValueChange={(value) => {
            setQuery(value)
            setOpen(true)
            if (field.value) {
              field.onChange('')
              setSelectedLabel('')
            }
          }}
          onFocus={() => setOpen(true)}
          placeholder={placeholder}
          aria-invalid={fieldState.invalid}
          className={cn(fieldState.invalid && 'border-destructive focus-visible:ring-destructive')}
        />
        {showPanel && (
          <CommandList>
            {!enabled ? (
              <p className="p-3 text-sm text-muted-foreground">{hintMessage}</p>
            ) : isFetching ? (
              <p className="p-3 text-sm text-muted-foreground">…</p>
            ) : options.length === 0 ? (
              <CommandEmpty>{emptyMessage}</CommandEmpty>
            ) : (
              options.map((option) => (
                <CommandItem
                  key={option.value}
                  value={option.value}
                  onSelect={() => {
                    field.onChange(option.value)
                    setSelectedLabel(option.label)
                    setQuery('')
                    setOpen(false)
                    onSelect?.(option)
                  }}
                >
                  <p className="truncate font-medium">{option.label}</p>
                  {option.description && <p className="truncate text-xs text-muted-foreground">{option.description}</p>}
                </CommandItem>
              ))
            )}
          </CommandList>
        )}
      </Command>
      {fieldState.error && <p className="text-xs text-destructive">{fieldState.error.message}</p>}
    </div>
  )
}
