import { useTranslation } from 'react-i18next'

/**
 * Deliberately minimal and low-visual-weight -- version + copyright
 * only, mirroring the same low-ceremony treatment
 * docs/ADMIN_DESIGN_SYSTEM.md §20.6 specifies for the Login
 * Experience's footer, so the two feel like the same product rather
 * than two different design decisions.
 */
export function Footer() {
  const { t } = useTranslation()
  const year = new Date().getFullYear()

  return (
    <footer className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-2 text-xs text-muted-foreground sm:px-6">
      <span>{t('shell.footer.copyright', { year })}</span>
      <span dir="ltr">v{__APP_VERSION__}</span>
    </footer>
  )
}
