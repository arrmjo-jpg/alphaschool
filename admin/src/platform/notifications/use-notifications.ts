import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { getNotificationProvider } from '@/platform/notifications/notification-provider'

/**
 * Polling-based refresh (ADR-0015 Decision 6/Alternatives) -- real-time
 * push depends on a broadcasting connection this milestone deliberately
 * does not stand up. Swapping to a realtime subscription later is an
 * additive change to this hook only.
 */
export function useNotifications() {
  return useQuery({
    queryKey: ['notifications'],
    queryFn: () => getNotificationProvider().fetchNotifications(),
    refetchInterval: 30_000,
  })
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => getNotificationProvider().markAsRead(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['notifications'] }),
  })
}
