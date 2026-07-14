import { create } from 'zustand'
import type { ReactNode } from 'react'

type ModalEntry = { id: string; content: ReactNode }

type ModalState = {
  stack: ModalEntry[]
  open: (id: string, content: ReactNode) => void
  close: (id: string) => void
}

/**
 * Imperative modal stacking (ADR-0015 execution plan, Modal framework).
 * ModalHost (mounted once in AppShell) renders the stack; any component
 * anywhere can push/pop without prop-drilling an "open" boolean through
 * a parent tree.
 */
export const useModalStore = create<ModalState>((set) => ({
  stack: [],
  open: (id, content) => set((state) => ({ stack: [...state.stack.filter((m) => m.id !== id), { id, content }] })),
  close: (id) => set((state) => ({ stack: state.stack.filter((m) => m.id !== id) })),
}))
