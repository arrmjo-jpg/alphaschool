import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from '@tanstack/react-router'
import { queryClient } from '@/lib/query-client'
import { router } from '@/platform/routing/router'
import { TooltipProvider } from '@/platform/components/ui/tooltip'
import { registerStaticCommands } from '@/platform/command-palette/register-static-commands'
import { registerConfigurationPlatformWorkspace } from '@/workspaces/administration-configuration/register'
import { registerProviderRegistryWorkspace } from '@/workspaces/administration-provider-registry/register'
import '@/platform/i18n'

registerStaticCommands()
registerConfigurationPlatformWorkspace()
registerProviderRegistryWorkspace()

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <RouterProvider router={router} />
      </TooltipProvider>
    </QueryClientProvider>
  )
}
