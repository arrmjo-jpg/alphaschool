import { create } from 'zustand'
import { persist } from 'zustand/middleware'

/**
 * Sidebar collapse state persists across sessions (docs/ADMIN_DESIGN_SYSTEM.md
 * §9 "predictable behavior") -- a returning user's expanded/collapsed
 * choice should hold, the same expectation the legacy admin's own
 * localStorage-backed toggle already met. Uses the same zustand+persist
 * shape as theme-store.ts rather than a plain useState in AppShell, so
 * the persistence concern lives with the state it persists.
 */
type SideNavState = {
  collapsed: boolean
  toggle: () => void
}

export const useSideNavStore = create<SideNavState>()(
  persist(
    (set) => ({
      collapsed: false,
      toggle: () => set((state) => ({ collapsed: !state.collapsed })),
    }),
    { name: 'admin-platform-sidenav' },
  ),
)
