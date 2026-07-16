import { useTranslation } from 'react-i18next'
import { useMe } from '@/platform/auth/use-me'
import { getQuickActions } from '@/platform/shell/quick-action-definition'
import { Button } from '@/platform/components/ui/button'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * Renders nothing when there is nothing permitted to show -- never a
 * placeholder or an empty bordered box (the legacy admin's own
 * dashboard QuickActions panel already got this right: "hides itself
 * if the user has zero permitted actions").
 */
export function QuickActions() {
  const { t } = useTranslation()
  const { data: me } = useMe()
  const permissions = me?.permissions ?? []
  const isSuperAdmin = me?.user.is_super_admin ?? false

  const visible = getQuickActions().filter(
    (action) => action.requiredPermission === null || isSuperAdmin || permissions.includes(action.requiredPermission),
  )

  if (visible.length === 0) return null

  return (
    <div className="flex items-center gap-1.5">
      {visible.map((action) => {
        const Icon = action.icon
        return (
          <Button key={action.id} variant="outline" size="sm" onClick={action.run} className="gap-1.5">
            <Icon className={ICON_SIZE.dense} />
            {t(action.labelKey, action.labelKey)}
          </Button>
        )
      })}
    </div>
  )
}
