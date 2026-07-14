import { useState } from 'react'
import { Outlet } from '@tanstack/react-router'
import { Menu } from 'lucide-react'
import { SideNav } from '@/platform/shell/side-nav'
import { TopBar } from '@/platform/shell/top-bar'
import { Sheet, SheetContent, SheetTitle } from '@/platform/components/ui/sheet'
import { Button } from '@/platform/components/ui/button'

/**
 * The reusable Workspace shell (docs/ADMIN_PLATFORM.md). Composes
 * everything a future workspace can rely on without ever being edited
 * itself when a workspace is added (ADR-0015 Decision 4) --
 * SideNav/TopBar read the registry and server access response; this
 * component only lays them out.
 */
export function AppShell() {
  const [collapsed, setCollapsed] = useState(false)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)

  return (
    <div className="flex h-screen flex-col">
      <div className="flex items-center border-b sm:hidden">
        <Button variant="ghost" size="icon" className="m-1" onClick={() => setMobileNavOpen(true)}>
          <Menu />
        </Button>
      </div>
      <div className="flex flex-1 overflow-hidden">
        <div className="hidden sm:block">
          <SideNav collapsed={collapsed} onToggle={() => setCollapsed((value) => !value)} />
        </div>
        <Sheet open={mobileNavOpen} onOpenChange={setMobileNavOpen}>
          <SheetContent side="start" className="p-0">
            <SheetTitle className="sr-only">Navigation</SheetTitle>
            <SideNav collapsed={false} onToggle={() => setMobileNavOpen(false)} />
          </SheetContent>
        </Sheet>
        <div className="flex flex-1 flex-col overflow-hidden">
          <div className="hidden sm:block">
            <TopBar />
          </div>
          <main className="flex flex-1 flex-col overflow-y-auto">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  )
}
