import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useToast } from '@/platform/toasts/toast-store'

/**
 * Closes docs/ADMIN_DESIGN_SYSTEM.md §M10/§9's long-standing rule
 * ("every mutation gets exactly one, explicit, consistent success
 * acknowledgment... never an implicit silent-redirect-means-success
 * pattern") -- no shared mutation wrapper guaranteeing this existed
 * anywhere in the codebase before UI Sprint 1 (confirmed by
 * inspection). A generic wrapper over React Query's useMutation so no
 * future page author can forget the toast, mirroring the double-signal
 * (toast + inline field error) Old Admin already got right for client
 * validation (§M10's own note) -- the error toast fires alongside
 * whatever field-level errors mapServerErrors separately renders, it
 * does not replace them.
 */
export function useEntityMutation<TData, TVariables>(options: {
  mutationFn: (vars: TVariables) => Promise<TData>
  successMessage: string
  invalidateQueryKey?: unknown[]
  onSuccess?: (data: TData) => void
  onError?: (error: unknown) => void
}) {
  const { t } = useTranslation('entity-workspace')
  const toast = useToast()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: options.mutationFn,
    onSuccess: (data) => {
      toast.success(options.successMessage)
      if (options.invalidateQueryKey) {
        queryClient.invalidateQueries({ queryKey: options.invalidateQueryKey })
      }
      options.onSuccess?.(data)
    },
    onError: (error) => {
      toast.error(t('mutation.genericError'))
      options.onError?.(error)
    },
  })
}
