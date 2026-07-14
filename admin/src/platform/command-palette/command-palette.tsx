import { useEffect, useState } from 'react'
import { Command } from 'cmdk'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useCommandPaletteStore } from '@/platform/command-palette/command-palette-store'
import { getCommands } from '@/platform/command-palette/command-registry'
import { searchAllProviders } from '@/platform/search/search-provider'

/**
 * Two command sources (ADR-0015 execution plan): static (navigation
 * shortcuts, theme toggle, logout) and dynamic, delegating to the same
 * SearchProvider registry as SearchBar -- additive UI over
 * infrastructure already built, not a separate system.
 */
export function CommandPalette() {
  const { t } = useTranslation()
  const isOpen = useCommandPaletteStore((state) => state.isOpen)
  const toggle = useCommandPaletteStore((state) => state.toggle)
  const close = useCommandPaletteStore((state) => state.close)
  const [search, setSearch] = useState('')

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault()
        toggle()
      }
      if (event.key === 'Escape') close()
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [toggle, close])

  const { data: dynamicResults = [] } = useQuery({
    queryKey: ['command-search', search],
    queryFn: () => searchAllProviders(search),
    enabled: isOpen && search.trim() !== '',
  })

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-[60] flex items-start justify-center bg-black/50 pt-24" onClick={close}>
      <div
        onClick={(event) => event.stopPropagation()}
        className="w-full max-w-lg overflow-hidden rounded-lg border bg-popover text-popover-foreground shadow-lg"
      >
        <Command label={t('shell.topbar.commandPalette')} shouldFilter={false}>
          <Command.Input
            autoFocus
            value={search}
            onValueChange={setSearch}
            placeholder={t('shell.commandPalette.placeholder')}
            className="w-full border-b bg-transparent px-4 py-3 text-sm outline-none"
          />
          <Command.List className="max-h-80 overflow-y-auto p-2">
            <Command.Empty className="p-4 text-center text-sm text-muted-foreground">
              {t('shell.commandPalette.noResults')}
            </Command.Empty>
            <Command.Group heading={t('shell.topbar.commandPalette')}>
              {getCommands()
                .filter((command) => t(command.labelKey, command.labelKey).toLowerCase().includes(search.toLowerCase()))
                .map((command) => (
                  <Command.Item
                    key={command.id}
                    onSelect={() => {
                      command.run()
                      close()
                    }}
                    className="cursor-pointer rounded-sm px-2 py-1.5 text-sm data-[selected=true]:bg-accent"
                  >
                    {t(command.labelKey, command.labelKey)}
                  </Command.Item>
                ))}
            </Command.Group>
            {dynamicResults.length > 0 && (
              <Command.Group heading="Search">
                {dynamicResults.map((result) => (
                  <Command.Item
                    key={result.id}
                    onSelect={close}
                    className="cursor-pointer rounded-sm px-2 py-1.5 text-sm data-[selected=true]:bg-accent"
                  >
                    {result.title}
                  </Command.Item>
                ))}
              </Command.Group>
            )}
          </Command.List>
        </Command>
      </div>
    </div>
  )
}
