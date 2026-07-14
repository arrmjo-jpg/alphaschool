import { useState } from 'react'
import { useQuery, keepPreviousData } from '@tanstack/react-query'
import { getCoreRowModel, useReactTable, type ColumnDef, type SortingState } from '@tanstack/react-table'
import type { ServerPage } from '@/platform/data-table/types'

type Options<T> = {
  queryKey: unknown[]
  queryFn: (params: { page: number; perPage: number; sort: string | null }) => Promise<ServerPage<T>>
  columns: ColumnDef<T, any>[]
  perPage?: number
}

/**
 * The DataTable framework's server-pagination contract (ADR-0015
 * execution plan): manual pagination/sorting delegated entirely to the
 * caller's queryFn, matching Laravel's standard paginated-resource
 * shape. A workspace only supplies queryFn + columns; loading/error/
 * empty state and pagination controls are DataTable's job, not the
 * workspace's.
 */
export function useServerDataTable<T>({ queryKey, queryFn, columns, perPage = 15 }: Options<T>) {
  const [page, setPage] = useState(1)
  const [sorting, setSorting] = useState<SortingState>([])

  const sort = sorting[0] ? `${sorting[0].desc ? '-' : ''}${sorting[0].id}` : null

  const query = useQuery({
    queryKey: [...queryKey, page, perPage, sort],
    queryFn: () => queryFn({ page, perPage, sort }),
    placeholderData: keepPreviousData,
  })

  const table = useReactTable({
    data: query.data?.data ?? [],
    columns,
    state: { sorting },
    onSortingChange: (updater) => {
      setPage(1)
      setSorting(updater)
    },
    manualPagination: true,
    manualSorting: true,
    getCoreRowModel: getCoreRowModel(),
  })

  return {
    table,
    query,
    page,
    setPage,
    meta: query.data?.meta,
  }
}
