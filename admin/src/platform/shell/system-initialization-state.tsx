import { Sparkles } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { ICON_SIZE } from '@/lib/icon-sizes'

/**
 * Distinct from EmptyWorkspaceState (docs/ADMIN_DESIGN_SYSTEM.md §25.2):
 * this fires when the local, static workspace registry itself is empty
 * -- no workspace module has been built into this deployment at all, a
 * deployment-level fact true for every user, never a permission gap.
 * Telling a fresh installation's own Super Admin to "contact your
 * administrator" mid-setup would be wrong, not merely unpolished, which
 * is why this is a separate component with separate copy rather than a
 * shared message. Not fake content or a placeholder widget -- a genuine
 * product-level onboarding state.
 */
export function SystemInitializationState() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-1 flex-col items-center justify-center gap-3 text-center">
      <Sparkles className={ICON_SIZE.prominent} aria-hidden="true" />
      <h2 className="text-base font-medium">{t('shell.systemInit.title')}</h2>
      <p className="max-w-sm text-sm text-muted-foreground">{t('shell.systemInit.body')}</p>
    </div>
  )
}
