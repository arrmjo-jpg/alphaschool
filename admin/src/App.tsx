import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'
import { queryClient } from '@/lib/query-client'
import { router } from '@/platform/routing/router'
import { TooltipProvider } from '@/platform/components/ui/tooltip'
import { registerStaticCommands } from '@/platform/command-palette/register-static-commands'
import '@/platform/i18n'

registerStaticCommands()

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <RouterProvider router={router} />
      </TooltipProvider>
    </QueryClientProvider>
  )
}
