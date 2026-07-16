import { useTranslation } from 'react-i18next'
import { Command as CommandIcon, Globe, Menu, Moon, Sun } from 'lucide-react'
import { setLocale } from '@/platform/i18n'
import { SearchBar } from '@/platform/search/search-bar'
import { NotificationCenter } from '@/platform/notifications/notification-center'
import { Breadcrumb } from '@/platform/shell/breadcrumb'
import { QuickActions } from '@/platform/shell/quick-actions'
import { UserMenu } from '@/platform/shell/user-menu'
import { Button } from '@/platform/components/ui/button'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/platform/components/ui/tooltip'
import { useThemeStore } from '@/platform/theme/theme-store'
import { useCommandPaletteStore } from '@/platform/command-palette/command-palette-store'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * Calm, minimal, every element earns its place
 * (docs/ADMIN_DESIGN_SYSTEM.md's explicit Topbar instruction) -- no
 * decorative search box (§M1's fix), no stub affordances. Sticky +
 * translucent backdrop-blur (§5's ported legacy-admin treatment).
 */
export function TopBar({ onOpenMobileNav }: { onOpenMobileNav: () => void }) {
  const { t, i18n } = useTranslation()
  const { mode, setMode } = useThemeStore()
  const openPalette = useCommandPaletteStore((state) => state.open)

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur sm:px-6">
      <Button
        variant="ghost"
        size="icon"
        className="shrink-0 lg:hidden"
        onClick={onOpenMobileNav}
        aria-label={t('shell.nav.openMenu', 'Open navigation')}
      >
        <Menu className={ICON_SIZE.default} />
      </Button>

      <div className="hidden min-w-0 sm:block">
        <Breadcrumb />
      </div>

      <div className="hidden md:block md:w-64 lg:w-80">
        <SearchBar />
      </div>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="outline"
            size="sm"
            className="shrink-0 gap-2"
            onClick={openPalette}
            aria-label={t('shell.topbar.commandPalette')}
          >
            <CommandIcon className={ICON_SIZE.dense} />
            <kbd className="hidden text-xs text-muted-foreground sm:inline" aria-hidden="true">
              ⌘K
            </kbd>
          </Button>
        </TooltipTrigger>
        <TooltipContent side="bottom">{t('shell.topbar.commandPalette')}</TooltipContent>
      </Tooltip>

      <div className="min-w-0 flex-1" />

      <div className="hidden lg:block">
        <QuickActions />
      </div>

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="hidden shrink-0 sm:inline-flex"
            aria-label={t('shell.topbar.language', 'Toggle language')}
            onClick={() => setLocale(i18n.language === 'ar' ? 'en' : 'ar')}
          >
            <Globe className={ICON_SIZE.default} />
          </Button>
        </TooltipTrigger>
        <TooltipContent side="bottom">{t('shell.topbar.language', 'Toggle language')}</TooltipContent>
      </Tooltip>

      <NotificationCenter />

      <Tooltip>
        <TooltipTrigger asChild>
          <Button
            variant="ghost"
            size="icon"
            className="shrink-0"
            aria-label={t('shell.topbar.theme')}
            onClick={() => setMode(mode === 'dark' ? 'light' : 'dark')}
          >
            {mode === 'dark' ? <Sun className={ICON_SIZE.default} /> : <Moon className={ICON_SIZE.default} />}
          </Button>
        </TooltipTrigger>
        <TooltipContent side="bottom">{t('shell.topbar.theme')}</TooltipContent>
      </Tooltip>

      <UserMenu />
    </header>
  )
}
