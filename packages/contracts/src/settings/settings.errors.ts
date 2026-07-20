// No Settings-specific error variant exists -- every failure this
// endpoint set can produce is already one of the common error schemas
// (Decision 5: common primitives are reused, never redefined). This
// file exists so the feature folder still matches the Request/Response/
// Error convention (Decision 3) with a real, findable file, not an
// implicit "errors live somewhere else."
export { ApiErrorSchema, ValidationErrorSchema, AuthorizationErrorSchema, ConflictErrorSchema, NotFoundErrorSchema } from '../common/errors'
