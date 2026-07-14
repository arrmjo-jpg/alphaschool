import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Search as SearchIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Input } from '@/platform/components/ui/input'
import { searchAllProviders } from '@/platform/search/search-provider'

export function SearchBar() {
  const { t } = useTranslation()
  const [query, setQuery] = useState('')
  const [debounced, setDebounced] = useState('')

  useEffect(() => {
    const timeout = setTimeout(() => setDebounced(query), 250)
    return () => clearTimeout(timeout)
  }, [query])

  const { data: results = [] } = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => searchAllProviders(debounced),
    enabled: debounced.trim() !== '',
  })

  return (
    <div className="relative w-full max-w-sm">
      <SearchIcon className="pointer-events-none absolute start-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
      <Input
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        placeholder={t('shell.topbar.search')}
        className="ps-8"
      />
      {debounced.trim() !== '' && (
        <div className="absolute z-40 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-md">
          {results.length === 0 ? (
            <p className="p-3 text-sm text-muted-foreground">{t('shell.commandPalette.noResults')}</p>
          ) : (
            <ul className="max-h-72 overflow-y-auto p-1">
              {results.map((result) => (
                <li key={result.id} className="cursor-pointer rounded-sm px-2 py-1.5 text-sm hover:bg-accent">
                  <p>{result.title}</p>
                  {result.subtitle && <p className="text-xs text-muted-foreground">{result.subtitle}</p>}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  )
}
