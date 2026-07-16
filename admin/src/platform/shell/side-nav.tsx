import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useRouterState } from '@tanstack/react-router'
import { ChevronDown, PanelLeftClose, PanelLeftOpen } from 'lucide-react'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { useSideNavStore } from '@/platform/shell/sidenav-store'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/platform/components/ui/tooltip'
import { cn } from '@/lib/cn'
import { ICON_SIZE } from '@/lib/icon-sizes'
import type { WorkspaceDefinition, WorkspaceGroup } from '@/platform/navigation/workspace-definition'

type Node =
  | { type: 'item'; workspace: WorkspaceDefinition }
  | { type: 'group'; group: WorkspaceGroup; items: WorkspaceDefinition[] }

/**
 * Flat workspaces render at the top level in registration order; two or
 * more workspaces sharing the same `group.key` cluster together at the
 * position of the FIRST one registered, rather than scattering --
 * predictable, order-stable output regardless of how the registry
 * itself is populated (docs/ADMIN_DESIGN_SYSTEM.md §8.2).
 */
function buildNodes(workspaces: WorkspaceDefinition[]): Node[] {
  const nodes: Node[] = []
  const groupSlot = new Map<string, number>()

  for (const workspace of workspaces) {
    if (!workspace.group) {
      nodes.push({ type: 'item', workspace })
      continue
    }

    const slot = groupSlot.get(workspace.group.key)
    if (slot === undefined) {
      groupSlot.set(workspace.group.key, nodes.length)
      nodes.push({ type: 'group', group: workspace.group, items: [workspace] })
    } else {
      const node = nodes[slot]
      if (node.type === 'group') node.items.push(workspace)
    }
  }

  return nodes
}

/**
 * Boundary-safe prefix match -- pathname "/w/identity-maintenance/x"
 * must never match workspace key "identity" just because the string
 * "identity-maintenance" happens to start with "identity". A real bug
 * class the legacy admin's own Sidebar.tsx already had to guard
 * against (its matchPath()); the naive `pathname.startsWith(base)` this
 * replaces would have silently highlighted the wrong workspace the
 * first time two keys shared a prefix.
 */
function isWorkspaceActive(pathname: string, key: string): boolean {
  const base = `/w/${key}`
  return pathname === base || pathname.startsWith(`${base}/`)
}

const focusRing =
  'outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset'

type SideNavProps = {
  /**
   * The mobile drawer (a full-width Sheet, never a collapsed rail)
   * always renders expanded regardless of the desktop rail's persisted
   * state -- overrides the store locally for this render only, without
   * writing back to it, so collapsing the drawer never silently
   * collapses the desktop rail too.
   */
  forceExpanded?: boolean
  onNavigate?: () => void
}

export function SideNav({ forceExpanded = false, onNavigate }: SideNavProps) {
  const { t, i18n } = useTranslation()
  // Radix's Tooltip `side` only accepts physical values -- the
  // collapsed rail's tooltip must point away from the rail into the
  // content area, which is physically "right" in LTR and "left" in
  // RTL. i18next's own dir() is the single source of truth already
  // driving document.documentElement.dir (platform/i18n/index.ts).
  const tooltipSide = i18n.dir() === 'rtl' ? 'left' : 'right'
  const { workspaces } = useVisibleWorkspaces()
  const storeCollapsed = useSideNavStore((state) => state.collapsed)
  const toggle = useSideNavStore((state) => state.toggle)
  const collapsed = forceExpanded ? false : storeCollapsed
  const pathname = useRouterState({ select: (state) => state.location.pathname })

  // zustand's persist middleware rehydrates collapsed state shortly
  // after mount, one render tick after the initial (default) paint --
  // a real, found bug: the very first user click after any page load
  // silently failed to visually apply its width change (regardless of
  // direction), while every click after that worked correctly. The
  // rehydration's own near-simultaneous style change was confusing the
  // transition's starting reference before a real user ever touched
  // it. Suppressing the transition until one frame after mount (well
  // past rehydration) means the first paint never animates -- correct,
  // since there is nothing to animate from yet -- and only a genuine
  // user-triggered toggle after that point transitions smoothly.
  const [transitionsReady, setTransitionsReady] = useState(false)
  useEffect(() => {
    const frame = requestAnimationFrame(() => setTransitionsReady(true))
    return () => cancelAnimationFrame(frame)
  }, [])

  const nodes = useMemo(() => buildNodes(workspaces), [workspaces])

  // Manual expand/collapse of a group is a session-scoped override --
  // reset the instant navigation leaves that group, so a stale manual
  // collapse never hides the page the user is currently on (design doc
  // §8.4, ported from the legacy admin's own Sidebar.tsx behavior).
  const [overrides, setOverrides] = useState<Record<string, boolean>>({})
  useEffect(() => setOverrides({}), [pathname])

  const groupIsActive = (items: WorkspaceDefinition[]) =>
    items.some((workspace) => isWorkspaceActive(pathname, workspace.key))

  const isGroupOpen = (node: Extract<Node, { type: 'group' }>) =>
    overrides[node.group.key] ?? groupIsActive(node.items)

  const toggleGroup = (node: Extract<Node, { type: 'group' }>) =>
    setOverrides((prev) => ({ ...prev, [node.group.key]: !isGroupOpen(node) }))

  const renderLink = (workspace: WorkspaceDefinition, indent: boolean) => {
    const Icon = workspace.icon
    const active = isWorkspaceActive(pathname, workspace.key)
    const label = t(workspace.labelKey, workspace.labelKey)

    const link = (
      <Link
        to="/w/$workspaceKey"
        params={{ workspaceKey: workspace.key }}
        onClick={onNavigate}
        aria-current={active ? 'page' : undefined}
        className={cn(
          'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
          focusRing,
          collapsed && 'justify-center px-0',
          indent && !collapsed && 'ps-9',
          active
            ? 'bg-primary/10 text-primary'
            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
        )}
      >
        <Icon className={cn(ICON_SIZE.default, 'shrink-0')} />
        {!collapsed && <span className="truncate">{label}</span>}
      </Link>
    )

    if (!collapsed) return <div key={workspace.key}>{link}</div>

    return (
      <Tooltip key={workspace.key}>
        <TooltipTrigger asChild>{link}</TooltipTrigger>
        <TooltipContent side={tooltipSide}>{label}</TooltipContent>
      </Tooltip>
    )
  }

  return (
    <nav
      aria-label={t('shell.nav.label', 'Primary')}
      // Plain rem values via inline style (not w-16/w-64 utility
      // classes) plus flex-none/min-w-0/overflow-hidden: fully
      // decouples nav's size from flex negotiation with its sibling
      // content column and from its own children's min-content size,
      // so the explicit width always wins regardless of collapsed
      // state or content changes.
      style={{ width: collapsed ? '4rem' : '16rem' }}
      className={cn(
        'flex h-full min-w-0 flex-none flex-col overflow-hidden border-e border-border bg-background',
        transitionsReady && 'transition-[width] duration-200',
      )}
    >
      <div className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
        {nodes.map((node) => {
          if (node.type === 'item') return renderLink(node.workspace, false)

          // Collapsed rail: groups flatten into their member icons with
          // tooltips -- there is no room for a group header + chevron
          // affordance at 16px width, matching the legacy admin's own
          // collapsed-mode behavior exactly.
          if (collapsed) {
            return (
              <div key={node.group.key} className="mt-3 flex flex-col gap-1">
                {node.items.map((workspace) => renderLink(workspace, false))}
              </div>
            )
          }

          const GroupIcon = node.group.icon
          const open = isGroupOpen(node)

          return (
            <div key={node.group.key} className="mt-3">
              <button
                type="button"
                onClick={() => toggleGroup(node)}
                aria-expanded={open}
                className={cn(
                  'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-foreground/80 transition-colors hover:bg-accent',
                  focusRing,
                )}
              >
                <GroupIcon className={cn(ICON_SIZE.default, 'shrink-0')} />
                <span className="flex-1 truncate text-start">{t(node.group.labelKey, node.group.labelKey)}</span>
                <ChevronDown
                  className={cn('size-4 shrink-0 transition-transform', open && 'rotate-180')}
                />
              </button>
              {open && (
                <div className="mt-1 flex flex-col gap-1">
                  {node.items.map((workspace) => renderLink(workspace, true))}
                </div>
              )}
            </div>
          )
        })}
      </div>

      {/* The mobile drawer closes via the Sheet's own affordance -- a
          second, redundant collapse control has no purpose there. */}
      {!forceExpanded && (
        <div className={cn('border-t border-border p-2', collapsed ? 'flex justify-center' : '')}>
          <button
            type="button"
            onClick={toggle}
            aria-label={t(collapsed ? 'shell.nav.expand' : 'shell.nav.collapse')}
            className={cn(
              'flex size-9 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
              focusRing,
              !collapsed && 'ms-auto',
            )}
          >
            {collapsed ? <PanelLeftOpen className={ICON_SIZE.default} /> : <PanelLeftClose className={ICON_SIZE.default} />}
          </button>
        </div>
      )}
    </nav>
  )
}
