export type SearchResult = {
  id: string
  title: string
  subtitle?: string
  path: string
}

/**
 * A workspace registers one of these per searchable scope (ADR-0015
 * Decision 6 -- frontend contract only). Backed by a mock provider
 * until the backend Scout-based search abstraction (Blueprint
 * Addendum D5) exists; swapping the mock for a real provider requires
 * no change to SearchBar or the command palette.
 */
export type SearchProviderDefinition = {
  scopeKey: string
  labelKey: string
  search: (query: string) => Promise<SearchResult[]>
}

const providers: SearchProviderDefinition[] = []

export function registerSearchProvider(provider: SearchProviderDefinition): void {
  providers.push(provider)
}

export function getSearchProviders(): SearchProviderDefinition[] {
  return providers
}

export async function searchAllProviders(query: string): Promise<SearchResult[]> {
  if (query.trim() === '') return []

  const results = await Promise.all(providers.map((provider) => provider.search(query)))
  return results.flat()
}
