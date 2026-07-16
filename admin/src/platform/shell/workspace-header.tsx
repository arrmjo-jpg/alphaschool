import type { ReactNode } from 'react'

/**
 * The reusable page-header primitive every future workspace page uses
 * (docs/ADMIN_DESIGN_SYSTEM.md §7's List/Form/Dashboard template
 * header, generalized once here rather than re-hand-rolled per page --
 * closes the exact duplication pattern the design doc's §M6/§17 flags
 * in the legacy admin, before it has the chance to start in this
 * codebase).
 */
export function WorkspaceHeader({
  title,
  description,
  actions,
}: {
  title: string
  description?: string
  actions?: ReactNode
}) {
  return (
    <header className="flex flex-wrap items-center justify-between gap-3">
      <div className="min-w-0">
        <h1 className="truncate text-2xl font-bold">{title}</h1>
        {description && <p className="text-sm text-muted-foreground">{description}</p>}
      </div>
      {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </header>
  )
}
