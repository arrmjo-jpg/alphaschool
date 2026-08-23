import * as React from 'react'
import { Command as CommandPrimitive } from 'cmdk'
import { cn } from '@/lib/cn'

/**
 * Thin styled wrappers over cmdk (docs/ADMIN_DESIGN_SYSTEM.md §31.8) --
 * `cmdk` itself was already a dependency, used exactly once
 * (`command-palette/command-palette.tsx`, a full-screen Dialog), never
 * before as a reusable UI primitive the way Radix's Select already has
 * one in `ui/select.tsx`. Styling matches the existing SearchBar's own
 * hand-rolled listbox (`platform/search/search-bar.tsx`) so a live
 * search combobox looks identical whether it's built on cmdk or not.
 */
export const Command = CommandPrimitive

export const CommandInput = React.forwardRef<
  React.ElementRef<typeof CommandPrimitive.Input>,
  React.ComponentPropsWithoutRef<typeof CommandPrimitive.Input>
>(({ className, ...props }, ref) => (
  <CommandPrimitive.Input
    ref={ref}
    className={cn(
      'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
      className,
    )}
    {...props}
  />
))
CommandInput.displayName = CommandPrimitive.Input.displayName

export const CommandList = React.forwardRef<
  React.ElementRef<typeof CommandPrimitive.List>,
  React.ComponentPropsWithoutRef<typeof CommandPrimitive.List>
>(({ className, ...props }, ref) => (
  <CommandPrimitive.List
    ref={ref}
    className={cn(
      'absolute z-40 mt-1.5 max-h-72 w-full overflow-y-auto rounded-md border border-border bg-popover p-1.5 text-popover-foreground shadow-soft-lg animate-fade-in',
      className,
    )}
    {...props}
  />
))
CommandList.displayName = CommandPrimitive.List.displayName

export const CommandEmpty = React.forwardRef<
  React.ElementRef<typeof CommandPrimitive.Empty>,
  React.ComponentPropsWithoutRef<typeof CommandPrimitive.Empty>
>(({ className, ...props }, ref) => (
  <CommandPrimitive.Empty ref={ref} className={cn('p-3 text-sm text-muted-foreground', className)} {...props} />
))
CommandEmpty.displayName = CommandPrimitive.Empty.displayName

export const CommandItem = React.forwardRef<
  React.ElementRef<typeof CommandPrimitive.Item>,
  React.ComponentPropsWithoutRef<typeof CommandPrimitive.Item>
>(({ className, ...props }, ref) => (
  <CommandPrimitive.Item
    ref={ref}
    className={cn(
      'cursor-pointer rounded-sm px-3 py-2 text-sm text-foreground outline-none data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground',
      className,
    )}
    {...props}
  />
))
CommandItem.displayName = CommandPrimitive.Item.displayName
