// No Providers-specific error variant exists -- every failure this
// endpoint set can produce is already one of the common error schemas
// (Decision 5: common primitives are reused, never redefined). Mirrors
// settings.errors.ts's identical reasoning.
export { ApiErrorSchema, ValidationErrorSchema, AuthorizationErrorSchema, ConflictErrorSchema, NotFoundErrorSchema } from '../common/errors'
