import { flexRender, type Table as ReactTable } from '@tanstack/react-table'
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react'
import { Table, TableHeader, TableBody, TableRow, TableHead, TableCell } from '@/platform/components/ui/table'
import { Button } from '@/platform/components/ui/button'

type Props<T> = {
  table: ReactTable<T>
  isLoading: boolean
  isError: boolean
  page: number
  lastPage: number
  onPageChange: (page: number) => void
  emptyMessage?: string
}

export function DataTable<T>({ table, isLoading, isError, page, lastPage, onPageChange, emptyMessage = 'No results.' }: Props<T>) {
  const rows = table.getRowModel().rows

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
          <TableBody>
            {isLoading ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center">
                  <Loader2 className="mx-auto size-5 animate-spin text-muted-foreground" />
                </TableCell>
              </TableRow>
            ) : isError ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center text-destructive">
                  Something went wrong loading this data.
                </TableCell>
              </TableRow>
            ) : rows.length === 0 ? (
              <TableRow>
                <TableCell colSpan={table.getAllColumns().length} className="h-24 text-center text-muted-foreground">
                  {emptyMessage}
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
      <div className="flex items-center justify-end gap-2">
        <span className="text-sm text-muted-foreground">
          Page {page} of {Math.max(lastPage, 1)}
        </span>
        <Button variant="outline" size="icon" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          <ChevronLeft />
        </Button>
        <Button variant="outline" size="icon" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>
          <ChevronRight />
        </Button>
      </div>
    </div>
  )
}
