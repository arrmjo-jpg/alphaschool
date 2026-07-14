import type { Notification } from '@/platform/notifications/types'

/**
 * The frontend Notification contract (ADR-0015 Decision 6) -- swapping
 * this mock for a real implementation backed by the eventual
 * Notification Engine (ADR-0013) requires no change to
 * NotificationCenter, only a new provider satisfying this interface.
 */
export type NotificationProvider = {
  fetchNotifications: () => Promise<Notification[]>
  markAsRead: (id: string) => Promise<void>
}

/** No real Notification Engine exists yet -- always returns zero notifications. */
export const mockNotificationProvider: NotificationProvider = {
  fetchNotifications: async () => [],
  markAsRead: async () => {},
}

let activeProvider: NotificationProvider = mockNotificationProvider

export function setNotificationProvider(provider: NotificationProvider): void {
  activeProvider = provider
}

export function getNotificationProvider(): NotificationProvider {
  return activeProvider
}
