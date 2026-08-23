import { create } from 'zustand'

export type ToastVariant = 'success' | 'error'

export type ToastEntry = {
  id: string
  variant: ToastVariant
  title: string
  description?: string
}

type ToastState = {
  toasts: ToastEntry[]
  push: (toast: Omit<ToastEntry, 'id'>) => void
  dismiss: (id: string) => void
}

/**
 * Imperative toast stacking, deliberately mirroring modal-store.ts's
 * shape (ADR-0015 execution plan's Modal framework) rather than a new
 * pattern -- any component anywhere can push without prop-drilling.
 * Closes docs/ADMIN_DESIGN_SYSTEM.md §M10/§9's long-standing rule
 * ("every mutation gets exactly one, explicit, consistent success
 * acknowledgment") -- no toast primitive of any kind existed anywhere
 * in this codebase before UI Sprint 1 (confirmed by inspection), so
 * this is its first real implementation, not a port of an existing one.
 */
export const useToastStore = create<ToastState>((set) => ({
  toasts: [],
  push: (toast) => set((state) => ({ toasts: [...state.toasts, { ...toast, id: `toast-${Date.now()}-${Math.random().toString(36).slice(2)}` }] })),
  dismiss: (id) => set((state) => ({ toasts: state.toasts.filter((t) => t.id !== id) })),
}))

export function useToast() {
  const push = useToastStore((state) => state.push)
  return {
    success: (title: string, description?: string) => push({ variant: 'success', title, description }),
    error: (title: string, description?: string) => push({ variant: 'error', title, description }),
  }
}
