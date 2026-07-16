import { useEffect } from 'react'
import { create } from 'zustand'

export type BreadcrumbSegment = {
  label: string
  /** Omit for the trail's own last/current segment -- never a link to itself. */
  href?: string
}

type BreadcrumbState = {
  segments: BreadcrumbSegment[]
  setSegments: (segments: BreadcrumbSegment[]) => void
  clearSegments: () => void
}

/**
 * A workspace page contributes trail segments beyond its own workspace
 * root without AppShell/Breadcrumb ever changing (the same extension-
 * point discipline as WorkspaceDefinition/WidgetDefinition) -- no
 * consumer exists yet (Phase B builds no business pages), this is the
 * mechanism a future one calls into via useBreadcrumbSegments below.
 */
export const useBreadcrumbStore = create<BreadcrumbState>((set) => ({
  segments: [],
  setSegments: (segments) => set({ segments }),
  clearSegments: () => set({ segments: [] }),
}))

/**
 * Call from a workspace's own page component. Segments clear
 * automatically on unmount so navigating away never leaves a stale
 * trail tail behind for the next page.
 */
export function useBreadcrumbSegments(segments: BreadcrumbSegment[]): void {
  const setSegments = useBreadcrumbStore((state) => state.setSegments)
  const clearSegments = useBreadcrumbStore((state) => state.clearSegments)

  const key = JSON.stringify(segments)

  useEffect(() => {
    setSegments(segments)
    return () => clearSegments()
    // Re-run only when the segments' actual content changes, not on
    // every re-render of the calling page (a new array literal each
    // time would otherwise thrash the store).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])
}
