import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'node:path'
import { readFileSync } from 'node:fs'

const { version } = JSON.parse(readFileSync(path.resolve(__dirname, './package.json'), 'utf-8'))

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    // Docker development environment (docs/developer/docker-development.md):
    // binds all interfaces, not just localhost, so the container's
    // published port is actually reachable from the host browser. Harmless
    // outside Docker too -- Vite still prints the local URL to use.
    host: true,
    // Docker Desktop on Windows doesn't reliably propagate native
    // filesystem change events from the host into the container across
    // the bind mount -- chokidar's default watcher silently never fires,
    // so an edited file only takes effect after a full container
    // restart, not HMR (found live: a CSS token edit stayed stale until
    // `docker compose restart vite`). Polling works everywhere,
    // including outside Docker, so this isn't Docker-conditional.
    watch: {
      usePolling: true,
      interval: 300,
    },
  },
  // Build-time-injected app version (docs/ADMIN_DESIGN_SYSTEM.md §20.6:
  // "never hand-maintained") -- Footer/Login Experience read this
  // instead of a duplicated, driftable literal.
  define: {
    __APP_VERSION__: JSON.stringify(version),
  },
})
