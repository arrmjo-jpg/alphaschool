import { describe, expect, it, vi, beforeEach } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClientProvider, QueryClient } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { Home } from 'lucide-react'
import { registerWorkspace, getRegisteredWorkspaces } from '@/workspaces/registry'
import { useVisibleWorkspaces } from '@/platform/navigation/use-visible-workspaces'
import { useAuthStore } from '@/platform/auth/auth-store'
import * as apiClient from '@/lib/api-client'

/**
 * Proves ADR-0015 Decision 4: a business module becomes visible in the
 * shell by registering exactly one WorkspaceDefinition -- no AppShell,
 * navigation, routing, or layout source file is touched to make this
 * test pass. SideNav and HomePage both already consume
 * useVisibleWorkspaces generically; this test exercises that same
 * hook a real workspace module would rely on unmodified.
 */
describe('workspace extension point', () => {
  beforeEach(() => {
    useAuthStore.getState().setToken('test-token')
  })

  it('registerWorkspace adds an entry the registry returns unmodified', () => {
    const before = getRegisteredWorkspaces().length

    registerWorkspace({
      key: 'synthetic-test-workspace',
      labelKey: 'Synthetic Test Workspace',
      icon: Home,
      requiredPermission: 'synthetic.view',
      navItems: [],
      loadComponent: async () => ({ default: () => null }),
    })

    expect(getRegisteredWorkspaces().length).toBe(before + 1)
    expect(getRegisteredWorkspaces().find((w) => w.key === 'synthetic-test-workspace')).toBeDefined()
  })

  it('a registered workspace becomes visible once the server grants its key -- no platform source changes required', async () => {
    registerWorkspace({
      key: 'synthetic-visible-workspace',
      labelKey: 'Synthetic Visible Workspace',
      icon: Home,
      requiredPermission: 'synthetic.view',
      navItems: [],
      loadComponent: async () => ({ default: () => null }),
    })

    vi.spyOn(apiClient, 'apiFetch').mockResolvedValue({
      workspaces: [{ key: 'synthetic-visible-workspace', required_permission: 'synthetic.view' }],
    })

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )

    const { result } = renderHook(() => useVisibleWorkspaces(), { wrapper })

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.workspaces.map((w) => w.key)).toContain('synthetic-visible-workspace')
  })

  it('a workspace registered locally but absent from the server response never becomes visible', async () => {
    registerWorkspace({
      key: 'synthetic-unlicensed-workspace',
      labelKey: 'Synthetic Unlicensed Workspace',
      icon: Home,
      requiredPermission: 'synthetic.view',
      navItems: [],
      loadComponent: async () => ({ default: () => null }),
    })

    vi.spyOn(apiClient, 'apiFetch').mockResolvedValue({ workspaces: [] })

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    )

    const { result } = renderHook(() => useVisibleWorkspaces(), { wrapper })

    await waitFor(() => expect(result.current.isLoading).toBe(false))

    expect(result.current.workspaces.map((w) => w.key)).not.toContain('synthetic-unlicensed-workspace')
  })
})
