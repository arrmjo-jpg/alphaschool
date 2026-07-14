import { useWorkspaceAccess } from '@/platform/auth/use-workspaces'
import { getRegisteredWorkspaces } from '@/workspaces/registry'
import type { WorkspaceDefinition } from '@/platform/navigation/workspace-definition'

/**
 * Intersects the server's authoritative "which keys can this user see"
 * response with the locally registered WorkspaceDefinitions (which
 * describe HOW a permitted workspace renders -- icon, nav, route).
 * A workspace registered locally but absent from the server response
 * (not licensed, or the user lacks the permission) never renders --
 * this is what keeps visibility a real, server-enforced decision
 * rather than a client-side convenience.
 */
export function useVisibleWorkspaces(): { workspaces: WorkspaceDefinition[]; isLoading: boolean } {
  const { data, isLoading } = useWorkspaceAccess()
  const allowedKeys = new Set((data?.workspaces ?? []).map((w) => w.key))

  return {
    workspaces: getRegisteredWorkspaces().filter((w) => allowedKeys.has(w.key)),
    isLoading,
  }
}
