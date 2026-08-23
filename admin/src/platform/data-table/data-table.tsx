import type { ReactNode } from 'react'
import { flexRender, type Table as ReactTable } from '@tanstack/react-table'
import { Loader2, RotateCw } from 'lucide-react'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/platform/components/ui/table'
import { Button } from '@/platform/components/ui/button'
import { Pagination } from '@/platform/data-table/pagination'
import { cn } from '@/lib/cn'

type Props<T> = {
  table: ReactTable<T>
  /** True only on the genuine first load -- shows the spinner row. */
  isLoading: boolean
  /**
   * True while a background refetch is in flight (filter/search/page
   * change) -- dims existing rows instead of blanking or re-spinning
   * them, per the already-frozen rule in docs/ADMIN_DESIGN_SYSTEM.md
   * §9 ("List pages never blank-and-reflow during a background
   * refetch"). Confirmed absent from this file before §28's Sprint 1
   * pass -- isLoading alone could not distinguish the two cases.
   */
  isFetching?: boolean
  isError: boolean
  /** Renders a Retry action in the error state -- confirmed absent before this pass. */
  onRetry?: () => void
  page: number
  lastPage: number
  onPageChange: (page: number) => void
  paginationLabel?: string
  /** Full custom empty-state content (e.g. a two-condition List empty state, §28.4). Falls back to emptyMessage. */
  emptyState?: ReactNode
  emptyMessage?: string
}

export function DataTable<T>({
  table,
  isLoading,
  isFetching = false,
  isError,
  onRetry,
  page,
  lastPage,
  onPageChange,
  paginationLabel = 'Pagination',
  emptyState,
  emptyMessage = 'No results.',
}: Props<T>) {
  const rows = table.getRowModel().rows
  const isDimmed = isFetching && !isLoading && !isError

  return (
    <div className="flex flex-col gap-3">
      <div className="rounded-none border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((headerGroup) => (
              <TableRow key={headerGroup.id}>
                {headerGroup.headers.map((header) => (
                  <TableHead
                    key={header.id}
                    className={header.column.getCanSort() ? 'cursor-pointer select-none' : undefined}
                    onClick={header.column.getToggleSortingHandler()}
                  >
                    {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody className={cn('transition-opacity duration-200', isDimmed && 'opacity-70')}>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                  <Loader2 className="mx-auto size-5 animate-spin text-muted-foreground" />
                </TableCell>
              </TableRow>
            ) : isError ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                  <div className="flex flex-col items-center justify-center gap-2 text-destructive">
                    <span>Something went wrong loading this data.</span>
                    {onRetry && (
                      <Button variant="outline" size="sm" onClick={onRetry} className="gap-1.5">
                        <RotateCw className="size-3.5" aria-hidden="true" />
                        Retry
                      </Button>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ) : rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center text-muted-foreground">
                  {emptyState ?? emptyMessage}
                </TableCell>
              </TableRow>
            ) : (
              rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</TableCell>
                  ))}
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </div>

      <Pagination page={page} lastPage={lastPage} onPageChange={onPageChange} label={paginationLabel} />
    </div>
  )
}
