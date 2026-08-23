import { useEffect, useState } from 'react'
import { Search } from 'lucide-react'
import { Input } from '@/platform/components/ui/input'
import { ICON_SIZE } from '@/lib/icon-sizes'

const DEBOUNCE_MS = 350

/**
 * List-scoped, debounced search (docs/ADMIN_DESIGN_SYSTEM.md §28.4) --
 * deliberately distinct from the global SearchBar (platform/search/),
 * which searches across every registered provider, not one entity
 * list. Owns its own draft/debounce state so a fast typist never
 * fires a request per keystroke; only calls onChange once the value
 * has settled, mirroring SearchBar's own inline debounce pattern
 * (no shared debounce hook exists in this codebase to reuse).
 */
export function SearchInput({ value, onChange, placeholder }: { value: string; onChange: (value: string) => void; placeholder: string }) {
  const [draft, setDraft] = useState(value)

  // Re-sync if the parent resets the committed value externally (e.g.
  // a "Clear all filters" action) without this component's own typing.
  useEffect(() => {
    setDraft(value)
  }, [value])

  useEffect(() => {
    const timeout = setTimeout(() => {
      if (draft !== value) onChange(draft)
    }, DEBOUNCE_MS)
    return () => clearTimeout(timeout)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [draft])

  return (
    <div className="relative min-w-0 flex-1 sm:max-w-xs">
      <Search className={ICON_SIZE.dense + ' pointer-events-none absolute start-2.5 top-1/2 -translate-y-1/2 text-muted-foreground'} aria-hidden="true" />
      <Input
        value={draft}
        onChange={(event) => setDraft(event.target.value)}
        placeholder={placeholder}
        aria-label={placeholder}
        className="ps-9"
      />
    </div>
  )
}
