import { lazy, Suspense } from 'react'
import { Loader2 } from 'lucide-react'
import { getRegisteredWorkspaces } from '@/workspaces/registry'
import { WorkspaceSubPathProvider } from '@/platform/navigation/workspace-sub-path'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

/**
 * The one generic route every registered workspace mounts under
 * (/w/$workspaceKey, and its sibling splat /w/$workspaceKey/$) -- this
 * file never changes when a workspace is added or removed (ADR-0015
 * Decision 4). What renders inside is entirely the workspace's own
 * lazy-loaded component. `subPath` (from the splat route, "" for the
 * exact-root route) is threaded through WorkspaceSubPathProvider so an
 * entity-CRUD workspace can read its own deep-linked state without
 * this file learning anything about entity-specific route shapes.
 */
export function WorkspaceRoutePage({ workspaceKey, subPath = '' }: { workspaceKey: string; subPath?: string }) {
  const workspace = getRegisteredWorkspaces().find((w) => w.key === workspaceKey)

  if (!workspace) {
    return <p className="p-6 text-sm text-muted-foreground">Unknown workspace.</p>
  }

  const LazyWorkspace = lazy(workspace.loadComponent)

  return (
    <WorkspaceSubPathProvider value={subPath}>
      <Suspense fallback={<Loader2 className={cn('m-8 animate-spin text-muted-foreground', ICON_SIZE.prominent)} />}>
        <LazyWorkspace />
      </Suspense>
    </WorkspaceSubPathProvider>
  )
}
