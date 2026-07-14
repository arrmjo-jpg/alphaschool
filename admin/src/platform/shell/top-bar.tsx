import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { Command as CommandIcon, Globe, LogOut, Moon, Sun } from 'lucide-react'
import { setLocale } from '@/platform/i18n'
import { SearchBar } from '@/platform/search/search-bar'
import { NotificationCenter } from '@/platform/notifications/notification-center'
import { Button } from '@/platform/components/ui/button'
import { Avatar, AvatarFallback } from '@/platform/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/platform/components/ui/dropdown-menu'
import { useAuthStore } from '@/platform/auth/auth-store'
import { useMe } from '@/platform/auth/use-me'
import { useThemeStore } from '@/platform/theme/theme-store'
import { useCommandPaletteStore } from '@/platform/command-palette/command-palette-store'

export function TopBar() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const logout = useAuthStore((state) => state.logout)
  const { data: me } = useMe()
  const { mode, setMode } = useThemeStore()
  const openPalette = useCommandPaletteStore((state) => state.open)

  const initials = me?.user.name?.en?.slice(0, 2).toUpperCase() ?? me?.user.username.slice(0, 2).toUpperCase() ?? '?'

  return (
    <header className="flex h-14 items-center gap-3 border-b bg-background px-4">
      <SearchBar />
      <Button variant="outline" size="sm" className="ms-2 gap-2" onClick={openPalette}>
        <CommandIcon className="size-4" />
        <kbd className="text-xs text-muted-foreground">⌘K</kbd>
      </Button>
      <div className="flex-1" />
      <Button
        variant="ghost"
        size="icon"
        aria-label="Toggle language"
        onClick={() => setLocale(i18n.language === 'ar' ? 'en' : 'ar')}
      >
        <Globe />
      </Button>
      <NotificationCenter />
      <Button
        variant="ghost"
        size="icon"
        aria-label={t('shell.topbar.theme')}
        onClick={() => setMode(mode === 'dark' ? 'light' : 'dark')}
      >
        {mode === 'dark' ? <Sun /> : <Moon />}
      </Button>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon" className="rounded-full">
            <Avatar>
              <AvatarFallback>{initials}</AvatarFallback>
            </Avatar>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>{me?.user.name?.en ?? me?.user.username}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onSelect={() => {
              logout()
              navigate({ to: '/login' })
            }}
          >
            <LogOut className="size-4" />
            {t('shell.topbar.logout')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </header>
  )
}
