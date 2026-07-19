import { useQuery } from '@tanstack/react-query'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'

/**
 * Real maintenance-mode detection (docs/ADMIN_DESIGN_SYSTEM.md §20.5),
 * not a simulated flag -- Laravel's own `PreventRequestsDuringMaintenance`
 * middleware (already live infrastructure, `php artisan down`) rejects
 * every `api/*` request with a 503 before routing/auth ever run. A
 * cheap, already-real unauthenticated GET is enough to observe that;
 * `/workspaces` is used because `/login` requires a POST body and `/me`
 * would otherwise return an uninteresting 401 we'd have to ignore.
 *
 * Deliberately bypasses `apiFetch`: its global side effect on any 401
 * is `useAuthStore.logout()`, which is correct for a real session
 * going stale but wrong here, since this probe's own expected outcome
 * *is* a 401. A real bug was found this way -- if this query was still
 * in flight (or refetched) after a successful login elsewhere, its
 * late-arriving 401 silently wiped the just-set token and bounced the
 * user back to /login. A plain fetch here means the only thing this
 * hook ever reacts to is the status code, never the shared auth store.
 */
export function useMaintenanceCheck(): { maintenanceMode: boolean; isChecking: boolean } {
  const { data, isPending } = useQuery({
    queryKey: ['maintenance-check'],
    queryFn: async () => {
      const response = await fetch(`${API_URL}/workspaces`, { headers: { Accept: 'application/json' } })
      return response.status
    },
    retry: false,
    staleTime: 30_000,
  })

  return { maintenanceMode: data === 503, isChecking: isPending }
}
