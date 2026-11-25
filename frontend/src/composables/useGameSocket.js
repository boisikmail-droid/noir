import { reactive, readonly } from 'vue'
import GameSocket from '#game-socket'
import { getOrCreatePlayer, savePlayer } from './player'
import { playForMessage, unlockAudio } from './sounds'

const state = reactive({
  connected: false,
  playerId: '',
  playerName: '',
  lobbies: [],
  artPacks: [],
  lobby: null,
  game: null,
  lastEvents: [],
  error: '',
  raisingHands: null,
})

let socket = null
let pending = []
let prevCurrentPlayerId = null

function wsUrl() {
  const env = import.meta.env.VITE_WS_URL
  if (env) return env
  if (location.protocol === 'https:') return `wss://${location.host}/ws`
  return `ws://${location.hostname}:8081`
}

function send(type, payload = {}) {
  const msg = JSON.stringify({ type, payload })
  if (socket && socket.readyState === GameSocket.OPEN) {
    socket.send(msg)
  } else {
    pending.push(msg)
  }
}

function handleMessage(data) {
  state.error = ''
  playForMessage(data, {
    playerId: state.playerId,
    prevCurrentPlayerId,
  })

  switch (data.type) {
    case 'connected':
      break
    case 'hello.ok':
      state.connected = true
      send('artpacks.list')
      break
    case 'artpacks.list':
      state.artPacks = data.payload.packs || []
      break
    case 'lobby.list':
      state.lobbies = data.payload.lobbies || []
      break
    case 'lobby.joined':
    case 'lobby.updated':
      state.lobby = data.payload.lobby
      break
    case 'lobby.left':
      state.lobby = null
      state.game = null
      prevCurrentPlayerId = null
      break
    case 'game.started':
      state.lobby = data.payload.lobby
      state.game = data.payload.game
      state.lastEvents = []
      prevCurrentPlayerId = data.payload.game?.currentPlayerId ?? null
      break
    case 'game.updated':
      state.game = data.payload.game
      state.lastEvents = data.payload.events || []
      {
        const inter = (data.payload.events || []).find((e) => e.type === 'interrogate')
        state.raisingHands = inter ? inter.raisingHands : null
        if (inter) {
          setTimeout(() => {
            state.raisingHands = null
          }, 4000)
        }
      }
      prevCurrentPlayerId = data.payload.game?.currentPlayerId ?? null
      break
    case 'error':
      state.error = data.payload?.message || 'Ошибка'
      break
    default:
      break
  }
}

export function useGameSocket() {
  function connect() {
    if (socket && (socket.readyState === GameSocket.OPEN || socket.readyState === GameSocket.CONNECTING)) {
      return
    }
    const player = getOrCreatePlayer()
    state.playerId = player.playerId
    state.playerName = player.playerName

    socket = new GameSocket(wsUrl())
    socket.onopen = () => {
      pending.forEach((m) => socket.send(m))
      pending = []
      if (state.playerName) {
        send('hello', {
          playerId: state.playerId,
          playerName: state.playerName,
        })
      }
    }
    socket.onmessage = (ev) => {
      try {
        handleMessage(JSON.parse(ev.data))
      } catch {
        state.error = 'Некорректный ответ сервера'
      }
    }
    socket.onclose = () => {
      state.connected = false
      setTimeout(connect, 1500)
    }
    socket.onerror = () => {
      state.error = 'Нет связи с игровым сервером'
    }
  }

  function setName(name) {
    unlockAudio()
    state.playerName = name.trim()
    const player = getOrCreatePlayer()
    player.playerName = state.playerName
    savePlayer(player)
    send('hello', {
      playerId: state.playerId || player.playerId,
      playerName: state.playerName,
    })
  }

  function refreshLobbies() {
    send('lobby.list')
  }

  function createLobby(name, maxPlayers, artPack = 'ink-minimal') {
    unlockAudio()
    send('lobby.create', {
      name,
      maxPlayers,
      artPack,
      playerName: state.playerName,
    })
  }

  function joinLobby(lobbyId) {
    unlockAudio()
    send('lobby.join', {
      lobbyId,
      playerName: state.playerName,
    })
  }

  function leaveLobby() {
    send('lobby.leave')
  }

  function startLobby() {
    unlockAudio()
    send('lobby.start')
  }

  function gameAction(actionPayload) {
    unlockAudio()
    send('game.action', actionPayload)
  }

  return {
    state: readonly(state),
    mutableState: state,
    connect,
    setName,
    refreshLobbies,
    createLobby,
    joinLobby,
    leaveLobby,
    startLobby,
    gameAction,
    send,
  }
}
