import * as React from 'react'
import { Slot } from '@radix-ui/react-slot'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/cn'

const buttonVariants = cva(
  // The [&_svg:not([class*='size-'])]:size-5 guard matters, not just
  // decoration: a plain [&_svg]:size-4 descendant rule has HIGHER
  // specificity than a size class on the icon itself (a descendant
  // selector beats a single class selector), so every icon explicitly
  // sized via ICON_SIZE (lib/icon-sizes.ts) inside a Button was
  // silently being forced back down to 16px regardless of what was
  // requested -- a real bug found while implementing the readability
  // pass, not a defensive guess. The :not() guard makes this only a
  // fallback for icons that don't declare their own size.
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50 [&_svg:not([class*='size-'])]:size-5 [&_svg]:pointer-events-none [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring",
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground hover:opacity-90',
        destructive: 'bg-destructive text-destructive-foreground hover:opacity-90',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        secondary: 'bg-secondary text-secondary-foreground hover:opacity-90',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
      },
      // Sizes bumped one step across the board (readability directive
      // -- users spend 6-8 hours/day in this product, many older,
      // many wearing glasses): a 24px icon (ICON_SIZE.default) needs
      // more than 6px of padding on each side to read as intentional
      // rather than cramped, and WCAG 2.5.5 favors larger click
      // targets generally, not just for touch.
      size: {
        default: 'h-10 px-4 py-2',
        sm: 'h-9 rounded-md px-3 text-sm',
        lg: 'h-11 rounded-md px-8',
        icon: 'size-10',
      },
    },
    defaultVariants: { variant: 'default', size: 'default' },
  },
)

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button'
    return <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />
  },
)
Button.displayName = 'Button'
