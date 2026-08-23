/**
 * Builds the splat segment (and TanStack Router Link/navigate props)
 * for a path inside the Assignments workspace -- mirrors
 * platform/entity-workspace/entity-route.ts's exact shape, but not
 * reused directly: Assignments' own segment shape is deeper (tab/
 * sectionId/id/action) than the entity pattern's (entityKey/id/edit),
 * per §30.2's own routes.
 */
export function assignmentsSplat(...segments: (string | number)[]): string {
  return segments.map(String).filter((part) => part !== '').join('/')
}

export function assignmentsLinkProps(...segments: (string | number)[]) {
  return {
    to: '/w/$workspaceKey/$' as const,
    params: { workspaceKey: 'assignments', _splat: assignmentsSplat(...segments) },
  }
}

/** A plain path string for consumers that need one (e.g. Breadcrumb's own `href?: string` contract), not the typed Link/navigate props above. */
export function assignmentsPathString(...segments: (string | number)[]): string {
  return `/w/assignments/${assignmentsSplat(...segments)}`
}
