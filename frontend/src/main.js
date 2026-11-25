import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { setupTelegram } from './telegram'
import './assets/main.css'

const app = createApp(App).use(router)

router.isReady().then(() => {
  app.mount('#app')
  setupTelegram()
})
