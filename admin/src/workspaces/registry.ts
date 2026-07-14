import type { WorkspaceDefinition } from '@/platform/navigation/workspace-definition'

/**
 * Zero workspaces registered by design -- the Admin Platform Foundation
 * ships infrastructure only (ADR-0015 Decision 2). The shell must
 * render correctly with this array empty; that is the milestone's
 * primary acceptance criterion (ADR-0015 Decision 5), not a
 * placeholder to fill in before shipping.
 *
 * A future business module (Identity, Students, Admissions, ...) adds
 * itself with one line here and nothing else changes anywhere in
 * src/platform.
 */
const workspaces: WorkspaceDefinition[] = []

export function registerWorkspace(definition: WorkspaceDefinition): void {
  workspaces.push(definition)
}

export function getRegisteredWorkspaces(): WorkspaceDefinition[] {
  return workspaces
}
