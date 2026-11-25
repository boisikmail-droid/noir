import { watch } from 'vue'
import { useGameSocket } from './composables/useGameSocket'

export function setupTelegram() {
  const webApp = window.Telegram?.WebApp
  if (!webApp || (!webApp.initData && webApp.platform === 'unknown')) return

  document.documentElement.classList.add('telegram-mini-app')
  if (webApp.isVersionAtLeast('6.1')) webApp.setBackgroundColor('#151312')
  if (webApp.isVersionAtLeast('7.0')) webApp.setHeaderColor('#151312')
  webApp.expand()
  webApp.ready()

  if (webApp.isVersionAtLeast('6.2')) {
    const { state } = useGameSocket()
    watch(
      () => Boolean(state.lobby && !state.game?.finished),
      (inLobby) => {
        if (inLobby) webApp.enableClosingConfirmation()
        else webApp.disableClosingConfirmation()
      },
      { immediate: true },
    )
  }
}
