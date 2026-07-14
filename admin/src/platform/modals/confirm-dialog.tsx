import { useCallback, useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/platform/components/ui/dialog'
import { Button } from '@/platform/components/ui/button'
import { useModalStore } from '@/platform/modals/modal-store'

type ConfirmOptions = {
  title: string
  description?: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'default' | 'destructive'
}

/**
 * Promise-based confirm helper, used anywhere a destructive action
 * needs a "are you sure" gate -- e.g. the eventual UI for the backend's
 * own MergeRequest rollback flow (Sprint 3.2) once one exists.
 */
export function useConfirm() {
  const openModal = useModalStore((state) => state.open)
  const closeModal = useModalStore((state) => state.close)

  return useCallback(
    (options: ConfirmOptions): Promise<boolean> =>
      new Promise((resolve) => {
        const id = `confirm-${Date.now()}`
        const settle = (result: boolean) => {
          closeModal(id)
          resolve(result)
        }

        openModal(
          id,
          <ConfirmDialogView options={options} onSettle={settle} />,
        )
      }),
    [openModal, closeModal],
  )
}

function ConfirmDialogView({ options, onSettle }: { options: ConfirmOptions; onSettle: (result: boolean) => void }) {
  const [open, setOpen] = useState(true)

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        setOpen(next)
        if (!next) onSettle(false)
      }}
    >
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{options.title}</DialogTitle>
          {options.description && <DialogDescription>{options.description}</DialogDescription>}
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" onClick={() => onSettle(false)}>
            {options.cancelLabel ?? 'Cancel'}
          </Button>
          <Button variant={options.variant === 'destructive' ? 'destructive' : 'default'} onClick={() => onSettle(true)}>
            {options.confirmLabel ?? 'Confirm'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
