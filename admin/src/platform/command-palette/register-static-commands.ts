import { registerCommand } from '@/platform/command-palette/command-registry'
import { useThemeStore } from '@/platform/theme/theme-store'
import { useAuthStore } from '@/platform/auth/auth-store'
import { router } from '@/platform/routing/router'

/** The static command source (ADR-0015 execution plan) -- called once at app startup. */
export function registerStaticCommands(): void {
  registerCommand({
    id: 'toggle-theme',
    labelKey: 'shell.topbar.theme',
    run: () => {
      const { mode, setMode } = useThemeStore.getState()
      setMode(mode === 'dark' ? 'light' : 'dark')
    },
  })

  registerCommand({
    id: 'logout',
    labelKey: 'shell.topbar.logout',
    run: () => {
      useAuthStore.getState().logout()
      router.navigate({ to: '/login' })
    },
  })
}
