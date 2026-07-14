import type { FieldValues, UseFormSetError, Path } from 'react-hook-form'
import { ApiError } from '@/lib/api-client'

/**
 * Translates Laravel's standard 422 shape ({ errors: { field: [msg] } })
 * into React Hook Form field errors. A stable contract since every
 * existing FormRequest in the backend already returns this shape.
 */
export function mapServerErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>): boolean {
  if (!(error instanceof ApiError) || Object.keys(error.fieldErrors).length === 0) {
    return false
  }

  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    setError(field as Path<T>, { type: 'server', message: messages[0] })
  }

  return true
}
