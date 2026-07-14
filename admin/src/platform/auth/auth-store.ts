import { create } from 'zustand'
import { persist } from 'zustand/middleware'

/**
 * Holds only the bearer token -- genuine client/session state. Who the
 * user is and what they can do (`/api/v1/me`) is server state, fetched
 * via TanStack Query (see use-me.ts), never duplicated here.
 */
type AuthState = {
  token: string | null
  setToken: (token: string | null) => void
  logout: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      token: null,
      setToken: (token) => set({ token }),
      logout: () => set({ token: null }),
    }),
    { name: 'admin-platform-auth' },
  ),
)
