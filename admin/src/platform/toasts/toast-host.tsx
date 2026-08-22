import { useEffect } from 'react'
import { CheckCircle2, XCircle, X } from 'lucide-react'
import { useToastStore } from '@/platform/toasts/toast-store'
import { Button } from '@/platform/components/ui/button'
import { ICON_SIZE } from '@/lib/icon-sizes'
import { cn } from '@/lib/cn'

const AUTO_DISMISS_MS = 5000

function ToastCard({ id, variant, title, description }: { id: string; variant: 'success' | 'error'; title: string; description?: string }) {
  const dismiss = useToastStore((state) => state.dismiss)

  useEffect(() => {
    const timer = setTimeout(() => dismiss(id), AUTO_DISMISS_MS)
    return () => clearTimeout(timer)
  }, [id, dismiss])

  const Icon = variant === 'success' ? CheckCircle2 : XCircle

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        'animate-fade-in flex w-full max-w-sm items-start gap-3 rounded-md border bg-background p-4 shadow-soft-lg',
        variant === 'success' ? 'border-success/30' : 'border-destructive/30',
      )}
    >
      <Icon className={cn(ICON_SIZE.dense, 'mt-0.5 shrink-0', variant === 'success' ? 'text-success' : 'text-destructive')} aria-hidden="true" />
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium">{title}</p>
        {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
      </div>
      <Button variant="ghost" size="icon" className="size-6 shrink-0" onClick={() => dismiss(id)} aria-label="Dismiss">
        <X className="size-4" />
      </Button>
    </div>
  )
}

/**
 * Mounted once at the true route root (router.tsx, alongside ModalHost)
 * -- toasts must survive route changes, not live inside AppShell.
 */
export function ToastHost() {
  const toasts = useToastStore((state) => state.toasts)

  if (toasts.length === 0) return null

  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-end gap-2 p-4 sm:inset-x-auto sm:right-0">
      {toasts.map((toast) => (
        <div key={toast.id} className="pointer-events-auto w-full sm:w-auto">
          <ToastCard {...toast} />
        </div>
      ))}
    </div>
  )
}
