/** The server-side pagination shape every Laravel paginated resource in this codebase already returns. */
export type ServerPage<T> = {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export type ServerTableParams = {
  page: number
  perPage: number
  sort: string | null
}
