import { Bell } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useNotifications } from '@/platform/notifications/use-notifications'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * A denser second presentation of the same honest-empty-state
 * NotificationCenter already proves (docs/ADMIN_DESIGN_SYSTEM.md
 * §25.1) -- same hook, same "you're all caught up" copy, no separate
 * mechanism or duplicated empty-state language to maintain.
 */
export function NotificationsSummary() {
  const { t } = useTranslation()
  const { data: notifications = [] } = useNotifications()
  const recent = notifications.slice(0, 5)

  return (
    <div className="flex flex-col gap-3 rounded-md border bg-card p-4 text-card-foreground">
      <h3 className="text-sm font-medium text-muted-foreground">{t('shell.topbar.notifications')}</h3>
      {recent.length === 0 ? (
        <div className="flex flex-col items-center gap-2 py-4 text-center">
          <Bell className={cn(ICON_SIZE.default, 'text-muted-foreground/50')} aria-hidden="true" />
          <p className="text-sm font-medium">{t('shell.notifications.empty')}</p>
          <p className="text-xs text-muted-foreground">{t('shell.notifications.emptyHint')}</p>
        </div>
      ) : (
        <ul className="flex flex-col gap-2">
          {recent.map((notification) => (
            <li key={notification.id} className="flex flex-col gap-0.5 text-sm">
              <span className="flex items-center gap-2 font-medium">
                {notification.readAt === null && <span className="size-1.5 shrink-0 rounded-full bg-primary" />}
                {notification.title}
              </span>
              {notification.body && <span className="text-xs text-muted-foreground">{notification.body}</span>}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
