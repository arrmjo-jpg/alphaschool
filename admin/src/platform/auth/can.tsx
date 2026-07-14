import type { ReactNode } from 'react'
import { useHasPermission } from '@/platform/auth/use-me'

/**
 * The frontend mirror of the backend's hasPermissionTo($permission,
 * 'sanctum') discipline (docs/adr/0009, applied throughout Identity
 * Maintenance) -- hides UI the API would reject anyway. Never the real
 * authorization boundary; every write still re-checks server-side.
 */
export function Can({ permission, children }: { permission: string; children: ReactNode }) {
  const allowed = useHasPermission(permission)

  return allowed ? <>{children}</> : null
}
