import { useTranslation } from 'react-i18next'
import { useNavigate } from '@tanstack/react-router'
import { LogOut } from 'lucide-react'
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
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * Extracted from top-bar.tsx (docs/ADMIN_DESIGN_SYSTEM.md names it as
 * its own shell element) -- deliberately only two items today
 * (identity display, logout). No "Profile" item until a real profile
 * page exists to route to -- a menu entry pointing nowhere is exactly
 * the kind of decorative-non-functional affordance the design doc's
 * §M1/§M2 findings exist to prevent from ever entering this codebase.
 */
export function UserMenu() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const logout = useAuthStore((state) => state.logout)
  const { data: me } = useMe()

  const initials =
    me?.user.name?.en?.slice(0, 2).toUpperCase() ?? me?.user.username.slice(0, 2).toUpperCase() ?? '?'

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="flex size-9 items-center justify-center rounded-full outline-none transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
          aria-label={t('shell.topbar.userMenu', 'Account menu')}
        >
          <Avatar>
            <AvatarFallback>{initials}</AvatarFallback>
          </Avatar>
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuLabel>
          <span className="block truncate font-medium">{me?.user.name?.en ?? me?.user.username}</span>
          <span className="block truncate text-xs font-normal text-muted-foreground" dir="ltr">
            {me?.user.email}
          </span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          onClick={() => {
            logout()
            navigate({ to: '/login' })
          }}
          className="text-destructive focus:bg-destructive/10 focus:text-destructive"
        >
          <LogOut className={ICON_SIZE.dense} />
          {t('shell.topbar.logout')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
