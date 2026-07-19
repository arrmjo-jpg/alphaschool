import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'

/**
 * The Settings/Configuration Template's own save bar (already frozen,
 * docs/ADMIN_DESIGN_SYSTEM.md §10 and §26.5) -- deliberately generic,
 * not Configuration-Platform-specific, so it is the reusable primitive
 * every future settings-shaped page (§26.8) reuses rather than
 * hand-rolling its own save-bar chrome. Sticky positioning is visual
 * only; keyboard tab order still follows normal DOM order (§26.11),
 * so this never traps or reorders focus on its own.
 */
export function StickyActionBar({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        'sticky bottom-0 -mx-4 flex items-center justify-end gap-2 border-t border-border bg-background/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6',
        className,
      )}
    >
      {children}
    </div>
  )
}
