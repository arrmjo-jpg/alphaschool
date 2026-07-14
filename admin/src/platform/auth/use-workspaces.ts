import { useQuery } from '@tanstack/react-query'
import { apiFetch } from '@/lib/api-client'
import { useAuthStore } from '@/platform/auth/auth-store'
import type { WorkspaceAccess } from '@/platform/auth/types'

/**
 * "Which workspace keys can the current user access" -- server-computed
 * from Permission Groups (docs/ADMIN_PLATFORM.md), never decided
 * client-side. See use-visible-workspaces.ts for how this is combined
 * with the local registry.
 */
export function useWorkspaceAccess() {
  const token = useAuthStore((state) => state.token)

  return useQuery({
    queryKey: ['workspaces'],
    queryFn: () => apiFetch<{ workspaces: WorkspaceAccess[] }>('/workspaces'),
    enabled: token !== null,
    staleTime: 60_000,
  })
}
