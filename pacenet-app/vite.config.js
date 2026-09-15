import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: '/app/',
  build: {
    outDir: '../pacenetintegratedapp/app',
    emptyOutDir: true,
  },
  server: {
    proxy: {
      '/api': {
        target: 'https://hy0045.my.id',
        changeOrigin: true,
        secure: false,
      }
    }
  }
})
