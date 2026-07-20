import { z } from 'zod'

/**
 * The error contract taxonomy (docs/adr/0023-zod-first-api-contracts.md
 * Decision 4). Defined once here, reused across every feature folder,
 * never redefined per-module (Decision 5's "common primitives" rule
 * extended to errors, which Decision 4 also treats as a shared,
 * project-wide contract). `RateLimitErrorSchema`/`ServerErrorSchema`
 * are deliberately not defined yet -- no endpoint in this codebase
 * throws either today, and Decision 4 does not require scaffolding the
 * full taxonomy ahead of a real consumer (ADR-0022 Decision 3's
 * discipline, carried into this package).
 */
export const ApiErrorSchema = z.object({
  message: z.string(),
})

export const ValidationErrorSchema = ApiErrorSchema.extend({
  errors: z.record(z.string(), z.array(z.string())),
})

export const AuthorizationErrorSchema = ApiErrorSchema

export const NotFoundErrorSchema = ApiErrorSchema

/** `currentVersion` lets a caller re-fetch and retry without a second round trip just to learn what changed. */
export const ConflictErrorSchema = ApiErrorSchema.extend({
  currentVersion: z.number().int(),
})

export type ApiError = z.infer<typeof ApiErrorSchema>
export type ValidationError = z.infer<typeof ValidationErrorSchema>
export type AuthorizationError = z.infer<typeof AuthorizationErrorSchema>
export type NotFoundError = z.infer<typeof NotFoundErrorSchema>
export type ConflictError = z.infer<typeof ConflictErrorSchema>
