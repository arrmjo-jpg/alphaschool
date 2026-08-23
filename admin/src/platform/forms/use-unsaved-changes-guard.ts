import { useBlocker } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { useConfirm } from '@/platform/modals/confirm-dialog'

/**
 * Closes docs/ADMIN_DESIGN_SYSTEM.md §M3/§9's long-standing rule
 * ("every form with any field checks isDirty before allowing
 * navigation away, no exceptions") -- confirmed absent anywhere in
 * this codebase before UI Sprint 1, including Configuration Platform
 * and Provider Registry's own save forms, which never previously
 * created this risk the way List<->Form navigation does. Blocks both
 * in-app navigation (any Link/navigate) and the browser tab/refresh
 * close (enableBeforeUnload, wired automatically by useBlocker when a
 * function+condition form is used), via the same useConfirm dialog
 * every other confirmation in this codebase already uses -- no new
 * confirmation mechanism invented.
 */
export function useUnsavedChangesGuard(isDirty: boolean): void {
  const { t } = useTranslation('entity-workspace')
  const confirm = useConfirm()

  useBlocker(
    // useBlocker's shouldBlockFn: true = block the navigation (stay),
    // false = let it proceed (leave). useConfirm resolves true when the
    // user clicks the confirm button ("Discard", i.e. they want to
    // leave) -- so the two booleans are inverted, not the same value.
    async () =>
      !(await confirm({
        title: t('form.unsavedGuard.title'),
        description: t('form.unsavedGuard.description'),
        confirmLabel: t('form.unsavedGuard.confirm'),
        cancelLabel: t('form.unsavedGuard.cancel'),
        variant: 'destructive',
      })),
    isDirty,
  )
}
