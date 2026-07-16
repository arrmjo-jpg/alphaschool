/**
 * The frozen Lucide Icons sizing scale (docs/ADMIN_DESIGN_SYSTEM.md
 * §19.3-19.4), revised one Tailwind step larger across the board per
 * an explicit readability directive: AlphaSchool ERP's primary users
 * (principals, administrative/finance/HR staff, teachers) spend 6-8
 * hours a day in the product, and a meaningful share are older users
 * who wear glasses -- the interface prioritizes readability over
 * compactness. Every icon-bearing component reaches for one of these
 * class strings rather than an ad hoc `size-*`, so the scale stays a
 * single source of truth instead of drifting per-author. Lucide's
 * default 2px stroke width is used unmodified everywhere (§19.4) --
 * there is no strokeWidth entry here because the correct value is
 * "don't pass one."
 */
export const ICON_SIZE = {
  /** Table/dense-data cells, inline row actions, toolbar buttons, user-menu items, form-field icons. */
  dense: 'size-5',
  /** Sidebar nav, topbar actions (search, command palette, notifications, theme/language), primary buttons. */
  default: 'size-6',
  /** Status panels (Loading/Error/Empty state), dashboard/KPI card icons -- the largest, most prominent context. */
  prominent: 'size-7',
} as const satisfies Record<string, string>
