import * as React from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import { cva, type VariantProps } from 'class-variance-authority'
import { X } from 'lucide-react'
import { cn } from '@/lib/cn'
import { ICON_SIZE } from '@/lib/icon-sizes'

export const Sheet = DialogPrimitive.Root
export const SheetTrigger = DialogPrimitive.Trigger
export const SheetClose = DialogPrimitive.Close

const SheetOverlay = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Overlay>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Overlay>
>(({ className, ...props }, ref) => (
  <DialogPrimitive.Overlay
    ref={ref}
    className={cn('fixed inset-0 z-50 bg-foreground/40 backdrop-blur-sm animate-fade-in', className)}
    {...props}
  />
))

/**
 * The mobile fallback for modals and the collapsed sidebar drawer
 * (Responsive behavior, ADR-0015 execution plan) -- side-anchored,
 * `start`/`end` logical sides so it flips correctly under RTL rather
 * than needing a separate mirrored variant.
 */
const sheetVariants = cva('fixed z-50 gap-4 bg-background p-6 shadow-soft-lg animate-fade-in', {
  variants: {
    side: {
      start: 'inset-y-0 start-0 h-full w-3/4 max-w-sm border-e border-border',
      end: 'inset-y-0 end-0 h-full w-3/4 max-w-sm border-s border-border',
      top: 'inset-x-0 top-0 border-b border-border',
      bottom: 'inset-x-0 bottom-0 border-t border-border',
    },
  },
  defaultVariants: { side: 'end' },
})

export const SheetContent = React.forwardRef<
  React.ElementRef<typeof DialogPrimitive.Content>,
  React.ComponentPropsWithoutRef<typeof DialogPrimitive.Content> & VariantProps<typeof sheetVariants>
>(({ side, className, children, ...props }, ref) => (
  <DialogPrimitive.Portal>
    <SheetOverlay />
    <DialogPrimitive.Content ref={ref} className={cn(sheetVariants({ side }), className)} {...props}>
      {children}
      <DialogPrimitive.Close className="absolute end-4 top-4 rounded-sm p-1 opacity-70 outline-none hover:bg-accent hover:opacity-100">
        <X className={ICON_SIZE.dense} />
        <span className="sr-only">Close</span>
      </DialogPrimitive.Close>
    </DialogPrimitive.Content>
  </DialogPrimitive.Portal>
))
SheetContent.displayName = DialogPrimitive.Content.displayName

export const SheetTitle = DialogPrimitive.Title
export const SheetDescription = DialogPrimitive.Description
