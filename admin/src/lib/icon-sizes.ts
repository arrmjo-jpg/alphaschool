/**
 * The frozen Lucide Icons sizing scale (docs/ADMIN_DESIGN_SYSTEM.md
 * §19.3-19.4). Every icon-bearing component reaches for one of these
 * class strings rather than an ad hoc `size-*`, so the scale stays a
 * single source of truth instead of drifting per-author. Lucide's
 * default 2px stroke width is used unmodified everywhere (§19.4) --
 * there is no strokeWidth entry here because the correct value is
 * "don't pass one."
 */
export const ICON_SIZE = {
  /** Table/dense-data cells, inline form-field icons, default buttons. */
  dense: 'size-4',
  /** Sidebar nav, topbar/toolbar actions, large buttons, dashboard/KPI badges. */
  default: 'size-5',
  /** Status panels (Loading/Error/Empty state) -- the one larger context. */
  statusPanel: 'size-6',
} as const satisfies Record<string, string>
