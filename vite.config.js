import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// أثناء التطوير: طلبات /api و /storage بتروح لسيرفر لارافل
const backend = process.env.BACKEND_URL || 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  server: {
    proxy: {
      '/api': { target: backend, changeOrigin: true },
      '/storage': { target: backend, changeOrigin: true }
    }
  }
})
