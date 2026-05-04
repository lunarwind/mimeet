import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import basicSsl from '@vitejs/plugin-basic-ssl'

// PR-QR Step 6: 啟用 vite basicSsl，讓本機 dev server 走自簽 HTTPS。
// 用途：區網裝置（手機真機）連 dev server 測 getUserMedia 必須 HTTPS context。
// production / staging 不受影響（走真實 Let's Encrypt 憑證）。
export default defineConfig({
  plugins: [vue(), tailwindcss(), basicSsl()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
  },
})
