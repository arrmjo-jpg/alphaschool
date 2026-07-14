export type Notification = {
  id: string
  category: string
  title: string
  body?: string
  createdAt: string
  readAt: string | null
}

/** Categories map to the workspace they surface in (docs/ADMIN_PLATFORM.md) -- empty until a real workspace registers one. */
const categoryToWorkspace = new Map<string, string>()

export function registerNotificationCategory(category: string, workspaceKey: string): void {
  categoryToWorkspace.set(category, workspaceKey)
}

export function workspaceForCategory(category: string): string | undefined {
  return categoryToWorkspace.get(category)
}
