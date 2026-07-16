import { useState } from 'react'
import { Outlet } from '@tanstack/react-router'
import { SideNav } from '@/platform/shell/side-nav'
import { TopBar } from '@/platform/shell/top-bar'
import { Footer } from '@/platform/shell/footer'
import { Sheet, SheetContent, SheetTitle } from '@/platform/components/ui/sheet'

/**
 * The reusable Workspace shell (docs/ADMIN_PLATFORM.md,
 * docs/ADMIN_DESIGN_SYSTEM.md Phase B). Composes everything a future
 * workspace can rely on without ever being edited itself when a
 * workspace is added (ADR-0015 Decision 4) -- SideNav/TopBar read the
 * registry and server access response; this component only lays them
 * out. Persistent sidebar from `lg` up; a full-width Sheet drawer below
 * it, matching the legacy admin's own breakpoint choice (its w-64
 * sidebar was never meant to coexist with a narrow viewport).
 */
export function AppShell() {
  const [mobileNavOpen, setMobileNavOpen] = useState(false)

  return (
    <div className="flex h-screen flex-col">
      <div className="flex flex-1 overflow-hidden">
        <div className="hidden lg:block">
          <SideNav />
        </div>

        <Sheet open={mobileNavOpen} onOpenChange={setMobileNavOpen}>
          <SheetContent side="start" className="w-64 max-w-[85vw] p-0">
            <SheetTitle className="sr-only">Navigation</SheetTitle>
            <SideNav forceExpanded onNavigate={() => setMobileNavOpen(false)} />
          </SheetContent>
        </Sheet>

        <div className="flex flex-1 flex-col overflow-hidden">
          <TopBar onOpenMobileNav={() => setMobileNavOpen(true)} />

          <main className="flex flex-1 flex-col overflow-y-auto">
            <div className="mx-auto flex w-full max-w-screen-2xl flex-1 flex-col p-4 animate-fade-in sm:p-6 lg:p-8">
              <Outlet />
            </div>
          </main>

          <Footer />
        </div>
      </div>
    </div>
  )
}
