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

export function NotificationCenter() {
  const { t } = useTranslation()
  const { data: notifications = [] } = useNotifications()
  const markAsRead = useMarkNotificationRead()
  const unreadCount = notifications.filter((n) => n.readAt === null).length

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('shell.topbar.notifications')} className="relative">
          <Bell />
          {unreadCount > 0 && (
            <span className="absolute end-1 top-1 flex size-2 rounded-full bg-destructive" />
          )}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>{t('shell.topbar.notifications')}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {notifications.length === 0 ? (
          <p className="p-3 text-sm text-muted-foreground">{t('shell.notifications.empty')}</p>
        ) : (
          notifications.map((notification) => (
            <button
              key={notification.id}
              onClick={() => markAsRead.mutate(notification.id)}
              className="flex w-full flex-col gap-0.5 rounded-sm p-2 text-start text-sm hover:bg-accent"
            >
              <span className="font-medium">{notification.title}</span>
              {notification.body && <span className="text-xs text-muted-foreground">{notification.body}</span>}
            </button>
          ))
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
