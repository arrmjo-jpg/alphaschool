/**
 * Builds the splat segment (and TanStack Router Link/navigate props)
 * for a path inside an entity-CRUD workspace -- the small, shared
 * convention every List/Detail/Form navigation call in this pattern
 * uses, so no page ever hand-assembles a path string itself.
 * "" (list) | "new" (create) | "$id" (detail) | "$id/edit" (edit).
 */
export function entitySplat(entityKey: string, ...segments: (string | number)[]): string {
  return [entityKey, ...segments.map(String)].filter((part) => part !== '').join('/')
}

export function entityLinkProps(workspaceKey: string, entityKey: string, ...segments: (string | number)[]) {
  return {
    to: '/w/$workspaceKey/$' as const,
    params: { workspaceKey, _splat: entitySplat(entityKey, ...segments) },
  }
}

/** A plain path string for consumers that need one (e.g. Breadcrumb's own `href?: string` contract), not the typed Link/navigate props above. */
export function entityPathString(workspaceKey: string, entityKey: string, ...segments: (string | number)[]): string {
  return `/w/${workspaceKey}/${entitySplat(entityKey, ...segments)}`
}
