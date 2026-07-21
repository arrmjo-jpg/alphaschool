/**
 * The package root -- feature code imports only from here (ADR-0023
 * Decision 7), never through deep relative paths into another
 * feature's internal files.
 */
export * from './common'
export * from './settings'
export * from './providers'
