import { useTranslation } from 'react-i18next'
import { ShieldAlert } from 'lucide-react'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

type Props = {
  maintenanceMode: boolean
  compact?: boolean
}

/**
 * The brand column (docs/ADMIN_DESIGN_SYSTEM.md §20.1-§20.6). Every
 * Configuration-driven layer this section specifies -- a custom logo
 * (§20.2), a background image/slider/video (§20.3), bilingual
 * Configuration copy and rotating messages (§20.4) -- has no real
 * Configuration schema behind it yet (Digital Experience, the owning
 * capability, is not built). Rather than fake that data, this renders
 * exactly the documented fallback state for each layer: the product
 * wordmark, the "none" background mode (solid --primary, explicitly
 * "always a valid, complete configuration on its own"), and static
 * i18n welcome copy. No rotating-message mechanism is built here --
 * an empty message array is a fully valid, complete state per §20.4,
 * and there is no real varying content yet to prove a rotation
 * mechanism actually rotates correctly.
 */
export function LoginBrandPanel({ maintenanceMode, compact = false }: Props) {
  const { t } = useTranslation()
  const year = new Date().getFullYear()

  return (
    <div
      className={cn(
        'relative flex shrink-0 flex-col justify-between overflow-hidden bg-primary text-primary-foreground',
        compact ? 'px-4 py-5' : 'flex-1 p-10',
      )}
    >
      {/* Radial-gradient scrim (§20.1) -- kept purely for text legibility over
          whatever the background layer resolves to; here that's a solid
          color, but the overlay stays so a future image/slider/video
          background needs no separate legibility treatment. */}
      <div
        className="pointer-events-none absolute inset-0"
        style={{ background: 'radial-gradient(circle at 30% 20%, rgb(255 255 255 / 0.12), transparent 60%)' }}
        aria-hidden="true"
      />

      <div className="relative flex items-center gap-2">
        <span className={cn('font-semibold tracking-tight', compact ? 'text-lg' : 'text-2xl')}>AlphaSchool</span>
        {!compact && <span className="text-sm font-medium text-primary-foreground/70">ERP</span>}
      </div>

      {!compact && (
        <div className="relative flex flex-col gap-3">
          {maintenanceMode ? (
            <div className="flex flex-col gap-2">
              <ShieldAlert className={ICON_SIZE.prominent} aria-hidden="true" />
              <h1 className="text-xl font-semibold">{t('shell.login.maintenanceTitle')}</h1>
              <p className="max-w-sm text-sm text-primary-foreground/80">{t('shell.login.maintenanceBody')}</p>
            </div>
          ) : (
            <div className="flex flex-col gap-2">
              <h1 className="text-2xl font-semibold">{t('shell.login.welcomeTitle')}</h1>
              <p className="max-w-sm text-sm text-primary-foreground/80">{t('shell.login.welcomeBody')}</p>
            </div>
          )}
        </div>
      )}

      {!compact && (
        <div className="relative flex flex-wrap items-center justify-between gap-2 text-xs text-primary-foreground/60">
          <span>{t('shell.footer.copyright', { year })}</span>
          <span dir="ltr">v{__APP_VERSION__}</span>
        </div>
      )}
    </div>
  )
}
