import type { ComponentType } from 'react'
import type { LucideIcon } from 'lucide-react'

/**
 * The extension point (ADR-0015 Decision 4). A future business module
 * becomes installable by registering exactly one of these in
 * src/workspaces/registry.ts -- no change to AppShell, navigation,
 * routing, or layout is permitted for it to appear. See
 * tests/extension-point.test.tsx for the automated proof.
 */
export type WorkspaceNavItem = {
  key: string
  labelKey: string
  icon: LucideIcon
  /** Relative to the workspace's own root, e.g. "" or "reports". */
  path: string
}

export type WorkspaceGroup = {
  /** Unique across all registered workspaces -- groups a SideNav renders together. */
  key: string
  labelKey: string
  icon: LucideIcon
}

export type WorkspaceDefinition = {
  /** Must match a key server's GET /api/v1/workspaces can return. */
  key: string
  labelKey: string
  icon: LucideIcon
  /** Advisory only -- real visibility is server-computed, see use-visible-workspaces.ts. */
  requiredPermission: string
  navItems: WorkspaceNavItem[]
  /** Lazy-loaded so an unregistered/unlicensed workspace never ships in the bundle a user loads. */
  loadComponent: () => Promise<{ default: ComponentType }>
  /**
   * Optional collapsible-group membership (docs/ADMIN_DESIGN_SYSTEM.md
   * §8.2) -- additive to the frozen extension point, added during Phase
   * B once a real IA (Administration's nine child capabilities) needed
   * it; a workspace with no `group` renders exactly as before, flat, at
   * the top level. Two registered workspaces sharing the same
   * `group.key` render together under one collapsible header; the group
   * metadata (label/icon) is read from whichever workspace declares it
   * first -- keep it identical across every member of a group.
   */
  group?: WorkspaceGroup
}
