import { Bell } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/platform/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/platform/components/ui/dropdown-menu'
import { useMarkNotificationRead, useNotifications } from '@/platform/notifications/use-notifications'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * Genuinely wired to a real query today (docs/ADMIN_DESIGN_SYSTEM.md
 * §M2's fix, not a stub): mockNotificationProvider always resolves an
 * empty array until a real Notification Engine exists, so the empty
 * state below is the *honest* current state of this feature, never a
 * placeholder standing in for missing data.
 */
export function NotificationCenter() {
  const { t } = useTranslation()
  const { data: notifications = [] } = useNotifications()
  const markAsRead = useMarkNotificationRead()
  const unreadCount = notifications.filter((notification) => notification.readAt === null).length

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('shell.topbar.notifications')} className="relative shrink-0">
          <Bell className={ICON_SIZE.default} />
          {unreadCount > 0 && (
            <span className="absolute end-1 top-1 flex size-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold leading-none text-destructive-foreground">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>{t('shell.topbar.notifications')}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {notifications.length === 0 ? (
          <div className="flex flex-col items-center gap-2 px-3 py-8 text-center">
            <Bell className="size-8 text-muted-foreground/50" />
            <p className="text-sm font-medium">{t('shell.notifications.empty')}</p>
            <p className="text-xs text-muted-foreground">{t('shell.notifications.emptyHint')}</p>
          </div>
        ) : (
          notifications.map((notification) => (
            <button
              key={notification.id}
              onClick={() => markAsRead.mutate(notification.id)}
              className="flex w-full flex-col gap-0.5 rounded-xl p-2.5 text-start text-sm outline-none transition-colors hover:bg-accent focus-visible:bg-accent"
            >
              <span className="flex items-center gap-2 font-medium">
                {notification.readAt === null && <span className="size-1.5 shrink-0 rounded-full bg-primary" />}
                {notification.title}
              </span>
              {notification.body && <span className="text-xs text-muted-foreground">{notification.body}</span>}
            </button>
          ))
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
