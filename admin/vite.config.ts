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
  },
  // Build-time-injected app version (docs/ADMIN_DESIGN_SYSTEM.md §20.6:
  // "never hand-maintained") -- Footer/Login Experience read this
  // instead of a duplicated, driftable literal.
  define: {
    __APP_VERSION__: JSON.stringify(version),
  },
})
