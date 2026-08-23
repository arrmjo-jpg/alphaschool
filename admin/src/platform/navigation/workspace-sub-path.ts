import { createContext, useContext } from 'react'

/**
 * Additive extension to the generic /w/$workspaceKey route (router.tsx):
 * a sibling splat route (/w/$workspaceKey/$) captures everything past
 * the workspace root and threads it down via this context, so an
 * entity-CRUD workspace's own component can interpret its own
 * sub-path (e.g. "academic-years/123/edit") without router.tsx or
 * WorkspaceRoutePage ever learning about entity-specific route shapes
 * -- the same "shared code never branches on entity identity" rule
 * docs/ADMIN_DESIGN_SYSTEM.md §28.8 already binds the List/Detail/Form
 * pattern to, extended one layer down into routing. A workspace that
 * never calls useWorkspaceSubPath (Configuration Platform, Provider
 * Registry) is entirely unaffected -- this is additive, not a change
 * to WorkspaceDefinition's contract.
 */
const WorkspaceSubPathContext = createContext<string>('')

export const WorkspaceSubPathProvider = WorkspaceSubPathContext.Provider

export function useWorkspaceSubPath(): string {
  return useContext(WorkspaceSubPathContext)
}
