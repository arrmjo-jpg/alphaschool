import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import { Search as SearchIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/platform/components/ui/input'
import { getSearchProviders, searchAllProviders } from '@/platform/search/search-provider'
import { cn } from '@/lib/cn'

/**
 * A real capability, not a decorative box (docs/ADMIN_DESIGN_SYSTEM.md
 * §M1's fix, generalized): every visible state is honest about what is
 * actually true right now.
 *   - Zero providers registered (today's actual state -- no workspace
 *     exists yet) shows a distinct "not connected to anything yet"
 *     message, never the generic "no results" a genuine zero-match
 *     search would show once providers exist.
 *   - Results are real navigation targets (SearchResult.path), not
 *     inert list items -- the legacy search bar's own results were
 *     never clickable to anywhere, a gap this closes.
 *   - Full keyboard operability: Arrow Up/Down move a highlighted
 *     result, Enter navigates to it, Escape closes -- required for
 *     "one of the defining productivity features," not optional
 *     polish.
 */
export function SearchBar() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [debounced, setDebounced] = useState('')
  const [open, setOpen] = useState(false)
  const [highlighted, setHighlighted] = useState(0)
  const containerRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const timeout = setTimeout(() => setDebounced(query), 250)
    return () => clearTimeout(timeout)
  }, [query])

  const hasProviders = getSearchProviders().length > 0

  const { data: results = [], isFetching } = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => searchAllProviders(debounced),
    enabled: hasProviders && debounced.trim() !== '',
  })

  useEffect(() => setHighlighted(0), [results])

  useEffect(() => {
    function onClickOutside(event: MouseEvent) {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClickOutside)
    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [])

  const showPanel = open && query.trim() !== ''

  function onKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Escape') {
      setOpen(false)
      return
    }
    if (!showPanel || results.length === 0) return

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setHighlighted((index) => (index + 1) % results.length)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setHighlighted((index) => (index - 1 + results.length) % results.length)
    } else if (event.key === 'Enter') {
      const target = results[highlighted]
      if (target) {
        event.preventDefault()
        navigate({ to: target.path })
        setOpen(false)
      }
    }
  }

  return (
    <div ref={containerRef} className="relative w-full">
      <SearchIcon className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        value={query}
        onChange={(event) => {
          setQuery(event.target.value)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={onKeyDown}
        placeholder={t('shell.topbar.search')}
        className="ps-8"
        role="combobox"
        aria-expanded={showPanel}
        aria-controls="search-results"
      />
      {showPanel && (
        <div
          id="search-results"
          role="listbox"
          className="absolute z-40 mt-1.5 w-full overflow-hidden rounded-2xl border border-border bg-popover text-popover-foreground shadow-soft-lg animate-fade-in"
        >
          {!hasProviders ? (
            <p className="p-4 text-sm text-muted-foreground">{t('shell.search.notConnected')}</p>
          ) : isFetching ? (
            <p className="p-4 text-sm text-muted-foreground">{t('shell.search.searching')}</p>
          ) : results.length === 0 ? (
            <p className="p-4 text-sm text-muted-foreground">{t('shell.commandPalette.noResults')}</p>
          ) : (
            <ul className="max-h-72 overflow-y-auto p-1.5">
              {results.map((result, index) => (
                <li key={result.id} role="option" aria-selected={index === highlighted}>
                  <Link
                    to={result.path}
                    onClick={() => setOpen(false)}
                    className={cn(
                      'block rounded-xl px-3 py-2 text-sm transition-colors',
                      index === highlighted ? 'bg-accent text-accent-foreground' : 'hover:bg-accent',
                    )}
                  >
                    <p className="truncate font-medium">{result.title}</p>
                    {result.subtitle && <p className="truncate text-xs text-muted-foreground">{result.subtitle}</p>}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
