import { useEffect, useState } from 'react'
import { Command } from 'cmdk'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { useCommandPaletteStore } from '@/platform/command-palette/command-palette-store'
import { getCommands } from '@/platform/command-palette/command-registry'
import { getSearchProviders, searchAllProviders } from '@/platform/search/search-provider'
import { cn } from '@/lib/cn'

/**
 * Rebuilt on Radix Dialog (docs/ADMIN_DESIGN_SYSTEM.md §11: never
 * hand-roll a modal overlay when Radix is one dependency away) instead
 * of the original fixed-position div -- focus trap, Escape handling,
 * and aria dialog semantics come from the audited primitive instead of
 * being re-implemented here. Two command sources (ADR-0015 execution
 * plan): static (navigation shortcuts, theme toggle, logout) and
 * dynamic, delegating to the same SearchProvider registry as
 * SearchBar -- additive UI over infrastructure already built, not a
 * separate system. Honest about zero search providers being registered
 * today, exactly like SearchBar (§M1's fix generalized).
 */
export function CommandPalette() {
  const { t } = useTranslation()
  const navigate = useNavigate()
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
    }
    document.addEventListener('keydown', onKeyDown)
    return () => document.removeEventListener('keydown', onKeyDown)
  }, [toggle])

  useEffect(() => {
    if (!isOpen) setSearch('')
  }, [isOpen])

  const hasProviders = getSearchProviders().length > 0

  const { data: dynamicResults = [] } = useQuery({
    queryKey: ['command-search', search],
    queryFn: () => searchAllProviders(search),
    enabled: hasProviders && isOpen && search.trim() !== '',
  })

  const matchingCommands = getCommands().filter((command) =>
    t(command.labelKey, command.labelKey).toLowerCase().includes(search.toLowerCase()),
  )

  return (
    <DialogPrimitive.Root open={isOpen} onOpenChange={(next) => (next ? undefined : close())}>
      <DialogPrimitive.Portal>
        <DialogPrimitive.Overlay className="fixed inset-0 z-[60] bg-foreground/40 backdrop-blur-sm animate-fade-in" />
        <DialogPrimitive.Content
          className="fixed start-1/2 top-24 z-[60] w-full max-w-lg -translate-x-1/2 overflow-hidden rounded-lg border border-border bg-popover text-popover-foreground shadow-soft-lg animate-fade-in rtl:translate-x-1/2"
          onOpenAutoFocus={(event) => event.preventDefault()}
        >
          <DialogPrimitive.Title className="sr-only">{t('shell.topbar.commandPalette')}</DialogPrimitive.Title>
          <DialogPrimitive.Description className="sr-only">
            {t('shell.commandPalette.placeholder')}
          </DialogPrimitive.Description>
          <Command label={t('shell.topbar.commandPalette')} shouldFilter={false}>
            <Command.Input
              autoFocus
              value={search}
              onValueChange={setSearch}
              placeholder={t('shell.commandPalette.placeholder')}
              className="w-full border-b border-border bg-transparent px-4 py-3 text-sm outline-none placeholder:text-muted-foreground/70"
            />
            <Command.List className="max-h-80 overflow-y-auto p-2">
              {matchingCommands.length === 0 && dynamicResults.length === 0 && (
                <Command.Empty className="p-6 text-center text-sm text-muted-foreground">
                  {!hasProviders && search.trim() !== ''
                    ? t('shell.search.notConnected')
                    : t('shell.commandPalette.noResults')}
                </Command.Empty>
              )}

              {matchingCommands.length > 0 && (
                <Command.Group
                  heading={t('shell.commandPalette.commands', 'Commands')}
                  className="px-2 py-1.5 text-xs font-medium text-muted-foreground [&_[cmdk-group-items]]:mt-1"
                >
                  {matchingCommands.map((command) => (
                    <Command.Item
                      key={command.id}
                      onSelect={() => {
                        command.run()
                        close()
                      }}
                      className={cn(
                        'flex cursor-pointer items-center justify-between rounded-sm px-3 py-2 text-sm text-foreground',
                        'data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground',
                      )}
                    >
                      {t(command.labelKey, command.labelKey)}
                      {command.shortcut && (
                        <kbd className="text-xs text-muted-foreground">{command.shortcut}</kbd>
                      )}
                    </Command.Item>
                  ))}
                </Command.Group>
              )}

              {dynamicResults.length > 0 && (
                <Command.Group
                  heading={t('shell.commandPalette.searchResults', 'Search results')}
                  className="mt-1 px-2 py-1.5 text-xs font-medium text-muted-foreground [&_[cmdk-group-items]]:mt-1"
                >
                  {dynamicResults.map((result) => (
                    <Command.Item
                      key={result.id}
                      onSelect={() => {
                        navigate({ to: result.path })
                        close()
                      }}
                      className="cursor-pointer rounded-sm px-3 py-2 text-sm text-foreground data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground"
                    >
                      <p className="truncate">{result.title}</p>
                      {result.subtitle && <p className="truncate text-xs text-muted-foreground">{result.subtitle}</p>}
                    </Command.Item>
                  ))}
                </Command.Group>
              )}
            </Command.List>
          </Command>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
