import { ChevronLeft, ChevronRight } from 'lucide-react'
import { Button } from '@/platform/components/ui/button'
import { cn } from '@/lib/cn'

/**
 * Numbered-with-ellipsis pagination, upgrading DataTable's original
 * prev/next-only control (docs/ADMIN_DESIGN_SYSTEM.md §6.3's
 * long-documented gap, §28.4 -- a Foundation-level fix landing before
 * dozens of future screens depend on the weaker version, not an
 * Academic-specific feature).
 */
function getPageNumbers(page: number, lastPage: number): (number | 'ellipsis')[] {
  if (lastPage <= 7) {
    return Array.from({ length: lastPage }, (_, index) => index + 1)
  }

  const keep = new Set<number>([1, lastPage, page - 1, page, page + 1].filter((p) => p >= 1 && p <= lastPage))
  const sorted = [...keep].sort((a, b) => a - b)

  const result: (number | 'ellipsis')[] = []
  let previous = 0
  for (const p of sorted) {
    if (previous && p - previous > 1) result.push('ellipsis')
    result.push(p)
    previous = p
  }
  return result
}

export function Pagination({
  page,
  lastPage,
  onPageChange,
  label,
}: {
  page: number
  lastPage: number
  onPageChange: (page: number) => void
  label: string
}) {
  const safeLastPage = Math.max(lastPage, 1)
  const pages = getPageNumbers(page, safeLastPage)

  return (
    <nav aria-label={label} className="flex items-center justify-end gap-1">
      <Button variant="outline" size="icon" disabled={page <= 1} onClick={() => onPageChange(page - 1)} aria-label="Previous page">
        <ChevronRight className="hidden rtl:block" />
        <ChevronLeft className="rtl:hidden" />
      </Button>

      {pages.map((entry, index) =>
        entry === 'ellipsis' ? (
          <span key={`ellipsis-${index}`} className="px-1.5 text-sm text-muted-foreground" aria-hidden="true">
            …
          </span>
        ) : (
          <Button
            key={entry}
            variant={entry === page ? 'default' : 'outline'}
            size="icon"
            className={cn('min-w-10', entry === page && 'pointer-events-none')}
            aria-current={entry === page ? 'page' : undefined}
            onClick={() => onPageChange(entry)}
          >
            {entry}
          </Button>
        ),
      )}

      <Button variant="outline" size="icon" disabled={page >= safeLastPage} onClick={() => onPageChange(page + 1)} aria-label="Next page">
        <ChevronLeft className="hidden rtl:block" />
        <ChevronRight className="rtl:hidden" />
      </Button>
    </nav>
  )
}
