import { useQuery } from '@tanstack/react-query'
import { apiFetch } from '@/lib/api-client'
import { useAuthStore } from '@/platform/auth/auth-store'
import type { MeResponse } from '@/platform/auth/types'

export const meQueryKey = ['me'] as const

export function useMe() {
  const token = useAuthStore((state) => state.token)

  return useQuery({
    queryKey: meQueryKey,
    queryFn: () => apiFetch<MeResponse>('/me'),
    enabled: token !== null,
    staleTime: 60_000,
  })
}

/**
 * Super Admin's is_super_admin bypasses gating client-side, mirroring
 * the backend's own Gate::before bypass -- real enforcement always
 * stays server-side regardless of what this returns.
 */
export function usePermissions(): { permissions: string[]; isSuperAdmin: boolean; isLoading: boolean } {
  const { data, isLoading } = useMe()

  return {
    permissions: data?.permissions ?? [],
    isSuperAdmin: data?.user.is_super_admin ?? false,
    isLoading,
  }
}

export function useHasPermission(permission: string): boolean {
  const { permissions, isSuperAdmin } = usePermissions()

  return isSuperAdmin || permissions.includes(permission)
}
