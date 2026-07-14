import { useAuthStore } from '@/platform/auth/auth-store'

const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[]>

  constructor(status: number, message: string, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

type RequestOptions = Omit<RequestInit, 'body'> & { body?: unknown }

/**
 * A thin fetch wrapper, not a full HTTP client dependency -- attaches
 * the Sanctum bearer token, parses Laravel's standard JSON error shape
 * (`{ message, errors }`) into a typed ApiError, and clears the auth
 * store on 401 so the router guard can redirect to /login.
 */
export async function apiFetch<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { token } = useAuthStore.getState()
  const { body, headers, ...rest } = options

  const response = await fetch(`${API_URL}${path}`, {
    ...rest,
    headers: {
      Accept: 'application/json',
      ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...headers,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (response.status === 401) {
    useAuthStore.getState().logout()
  }

  if (!response.ok) {
    const payload = await response.json().catch(() => ({}))
    throw new ApiError(response.status, payload.message ?? response.statusText, payload.errors ?? {})
  }

  if (response.status === 204) {
    return undefined as T
  }

  return (await response.json()) as T
}
