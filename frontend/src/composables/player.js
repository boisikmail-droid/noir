import { isUiTest } from '../uiTest'

const PLAYER_KEY = isUiTest ? 'noir_ui_test_player' : 'noir_player'

export function getOrCreatePlayer() {
  const raw = localStorage.getItem(PLAYER_KEY)
  if (raw) {
    try {
      return JSON.parse(raw)
    } catch {
      /* ignore */
    }
  }
  const player = {
    playerId: crypto.randomUUID(),
    playerName: '',
  }
  localStorage.setItem(PLAYER_KEY, JSON.stringify(player))
  return player
}

export function savePlayer(player) {
  localStorage.setItem(PLAYER_KEY, JSON.stringify(player))
}
