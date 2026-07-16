import { lazy, Suspense } from 'react'
import { Loader2 } from 'lucide-react'
import { getRegisteredWorkspaces } from '@/workspaces/registry'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

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
    <Suspense fallback={<Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />}>
      <LazyWorkspace />
    </Suspense>
  )
}
