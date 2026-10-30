import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    watch: process.env.CHOKIDAR_USEPOLLING === 'true' ? { usePolling: true, interval: 500 } : undefined,
    proxy: {
      '/storage': {
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://api:80',
        changeOrigin: true,
      },
      '/api': {
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://api:80',
        changeOrigin: true,
      },
      '/sanctum': {
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://api:80',
        changeOrigin: true,
      },
    },
  },
})
