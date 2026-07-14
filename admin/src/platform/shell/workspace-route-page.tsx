import { lazy, Suspense } from 'react'
import { Loader2 } from 'lucide-react'
import { getRegisteredWorkspaces } from '@/workspaces/registry'

/**
 * The one generic route every registered workspace mounts under
 * (/w/$workspaceKey) -- this file never changes when a workspace is
 * added or removed (ADR-0015 Decision 4). What renders inside is
 * entirely the workspace's own lazy-loaded component.
 */
export function WorkspaceRoutePage({ workspaceKey }: { workspaceKey: string }) {
  const workspace = getRegisteredWorkspaces().find((w) => w.key === workspaceKey)

  if (!workspace) {
    return <p className="p-6 text-sm text-muted-foreground">Unknown workspace.</p>
  }

  const LazyWorkspace = lazy(workspace.loadComponent)

  return (
    <Suspense fallback={<Loader2 className="m-8 size-6 animate-spin text-muted-foreground" />}>
      <LazyWorkspace />
    </Suspense>
  )
}
