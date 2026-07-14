import { create } from 'zustand'
import { persist } from 'zustand/middleware'

export type ThemeMode = 'light' | 'dark' | 'system'

type ThemeState = {
  mode: ThemeMode
  setMode: (mode: ThemeMode) => void
}

function applyTheme(mode: ThemeMode): void {
  const root = document.documentElement
  if (mode === 'system') {
    root.removeAttribute('data-theme')
  } else {
    root.setAttribute('data-theme', mode)
  }
}

export const useThemeStore = create<ThemeState>()(
  persist(
    (set) => ({
      mode: 'system',
      setMode: (mode) => {
        applyTheme(mode)
        set({ mode })
      },
    }),
    {
      name: 'admin-platform-theme',
      onRehydrateStorage: () => (state) => {
        if (state) applyTheme(state.mode)
      },
    },
  ),
)

/**
 * Per-organization brand override slot (ADR-0006 dedicated-instance
 * model) -- overrides the one token (`--primary`) a customer's
 * branding is expected to touch. Every other design token stays fixed
 * across deployments. Not wired to a real backend config source yet
 * (Administration Platform, ADR-0011, owns that eventually); callable
 * today with a literal value for local theming/testing.
 */
export function setBrandPrimaryColor(cssColorValue: string): void {
  document.documentElement.style.setProperty('--primary', cssColorValue)
}
