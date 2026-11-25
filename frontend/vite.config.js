import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ command, mode }) => {
  const uiTest = command === 'serve' && mode === 'ui-test'
  const socketModule = uiTest ? 'src/testing/PreviewSocket.js' : 'src/composables/browserSocket.js'

  return {
    plugins: [
      vue(),
      {
        name: 'noir-telegram-sdk',
        transformIndexHtml() {
          if (uiTest) return
          return [{
            tag: 'script',
            attrs: { defer: true, src: 'https://telegram.org/js/telegram-web-app.js?65' },
            injectTo: 'head-prepend',
          }]
        },
      },
    ],
    base: command === 'serve' ? '/' : '/dist/',
    resolve: {
      alias: { '#game-socket': resolve(__dirname, socketModule) },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      proxy: {
        '/api': 'http://localhost:8000',
      },
    },
    build: {
      outDir: resolve(__dirname, '../backend/public/dist'),
      emptyOutDir: true,
    },
  }
})
