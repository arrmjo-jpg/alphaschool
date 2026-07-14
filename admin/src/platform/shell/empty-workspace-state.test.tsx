import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import '@/platform/i18n'
import { EmptyWorkspaceState } from '@/platform/shell/empty-workspace-state'

/** ADR-0015 Decision 5: zero registered workspaces is the primary acceptance state, not an edge case. */
describe('EmptyWorkspaceState', () => {
  it('renders a correct, intentional empty state', () => {
    render(<EmptyWorkspaceState />)

    expect(screen.getByText('No workspaces available')).toBeInTheDocument()
  })
})
