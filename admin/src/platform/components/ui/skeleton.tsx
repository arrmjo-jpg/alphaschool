import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

/**
 * Single-purpose (docs/ADMIN_DESIGN_SYSTEM.md §10) -- no size variants;
 * consumers pass an explicit height and width via className, matching
 * the legacy admin's own convention exactly.
 */
export function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn('animate-pulse rounded-md bg-muted', className)} {...props} />
}
