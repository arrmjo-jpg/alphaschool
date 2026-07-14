import { useModalStore } from '@/platform/modals/modal-store'

/** Mounted once in AppShell -- renders whatever the modal stack holds. */
export function ModalHost() {
  const stack = useModalStore((state) => state.stack)

  return <>{stack.map((modal) => <div key={modal.id}>{modal.content}</div>)}</>
}
