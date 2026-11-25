import { noir, sao } from './cards'

const artPacks = [
  { id: 'ink-minimal', label: 'Чернильный минимализм', cards: noir },
  { id: 'brush-flat', label: 'Плоская кисть', cards: noir },
  { id: 'sao-anime', label: 'Sword Art Online', cards: sao },
]

const botNames = ['Лиса', 'Сова', 'Ворон', 'Филин', 'Рысь', 'Тень', 'Искра', 'Шторм']
const bots = botNames.map((name, index) => ({ id: `bot-${index + 1}`, name }))

function makeLobby(id, name, maxPlayers, artPack, players, playing = false) {
  const hostPlayerId = players[0].id
  return {
    id,
    name,
    maxPlayers,
    status: playing ? 'playing' : 'gathering',
    statusLabel: playing ? 'Игра идёт' : 'Сбор игроков',
    canJoin: !playing && players.length < maxPlayers,
    playerCount: players.length,
    hostPlayerId,
    players: players.map((player) => ({ ...player, isHost: player.id === hostPlayerId })),
    artPack,
    artPackLabel: artPacks.find((pack) => pack.id === artPack).label,
  }
}

const sampleLobbies = [
  makeLobby('sample', 'Тестовое лобби', 4, 'ink-minimal', bots.slice(0, 2)),
  makeLobby('playing', 'Партия уже началась', 6, 'brush-flat', bots.slice(0, 6), true),
]

function makeGame(lobby, player) {
  const count = lobby.playerCount
  const size = count <= 4 ? 5 : count <= 6 ? 6 : 7
  const cards = artPacks.find((pack) => pack.id === lobby.artPack).cards
  const board = Array.from({ length: size }, (_, row) =>
    cards.slice(row * size, (row + 1) * size).map(([suspectId, name]) => ({
      suspectId,
      name,
      alive: true,
    })),
  )
  const center = Math.floor(size / 2)
  const identity = board[center][center]
  const teamMode = count >= 6
  return {
    size,
    board,
    artPack: lobby.artPack,
    players: lobby.players.map((entry, index) => ({
      id: entry.id,
      name: entry.name,
      team: teamMode ? index % (count === 9 ? 3 : 2) : null,
      trophyCount: 0,
      ...(entry.id === player.id ? { identityId: identity.suspectId, identityName: identity.name } : {}),
    })),
    currentPlayerId: player.id,
    trophiesToWin: count === 3 || count === 9 ? 4 : 3,
    teamMode,
    finished: false,
    canUpdate: true,
    log: [{ message: 'Тестовая партия началась' }],
  }
}

function adjacentIds(board, identityId) {
  const row = board.findIndex((cards) => cards.some((card) => card.suspectId === identityId))
  const col = board[row].findIndex((card) => card.suspectId === identityId)
  return [[0, 0], [-1, 0], [1, 0], [0, -1], [0, 1]]
    .map(([dr, dc]) => board[row + dr]?.[col + dc]?.suspectId)
    .filter(Boolean)
}

function shiftBoard(board, { axis, index, direction }) {
  const size = board.length
  const offset = direction === 'forward' ? 1 : size - 1
  return board.map((row, r) => row.map((card, c) => {
    if (axis === 'row' && r === index) return board[r][(c + size - offset) % size]
    if (axis === 'col' && c === index) return board[(r + size - offset) % size][c]
    return card
  }))
}

// Только интерфейс. Ответы имитируют протокол, ходы ботов пропускаются.
export default class PreviewSocket {
  static CONNECTING = 0
  static OPEN = 1

  readyState = PreviewSocket.CONNECTING
  player = null
  lobby = null
  game = null

  constructor() {
    queueMicrotask(() => {
      this.readyState = PreviewSocket.OPEN
      this.onopen?.()
    })
  }

  emit(type, payload = {}) {
    const data = JSON.stringify({ type, payload })
    queueMicrotask(() => this.onmessage?.({ data }))
  }

  send(message) {
    const { type, payload } = JSON.parse(message)
    switch (type) {
      case 'hello':
        this.player = { id: payload.playerId, name: payload.playerName }
        this.emit('hello.ok')
        this.emit('lobby.list', { lobbies: sampleLobbies })
        break
      case 'artpacks.list':
        this.emit('artpacks.list', {
          packs: artPacks.map(({ id, label, cards }) => ({
            id,
            label,
            preview: cards.slice(0, 5).map(([id, name]) => ({ id, name })),
          })),
        })
        break
      case 'lobby.list':
        this.emit('lobby.list', { lobbies: sampleLobbies })
        break
      case 'lobby.create':
        this.lobby = makeLobby('created', payload.name, payload.maxPlayers, payload.artPack,
          [this.player, ...bots.slice(0, payload.maxPlayers - 1)])
        this.game = null
        this.emit('lobby.joined', { lobby: this.lobby })
        break
      case 'lobby.join': {
        const sample = sampleLobbies.find((lobby) => lobby.id === payload.lobbyId && lobby.canJoin)
        if (!sample) return this.emit('error', { message: 'Лобби закрыто' })
        this.lobby = makeLobby(sample.id, sample.name, sample.maxPlayers, sample.artPack,
          [...sample.players, this.player])
        this.game = null
        this.emit('lobby.joined', { lobby: this.lobby })
        break
      }
      case 'lobby.leave':
        this.lobby = null
        this.game = null
        this.emit('lobby.left')
        break
      case 'lobby.start':
        if (!this.lobby || this.lobby.hostPlayerId !== this.player.id) break
        this.lobby = { ...this.lobby, status: 'playing', statusLabel: 'Игра идёт', canJoin: false }
        this.game = makeGame(this.lobby, this.player)
        this.emit('game.started', { lobby: this.lobby, game: this.gameState() })
        break
      case 'game.state':
        if (this.game) this.emit('game.updated', { game: this.gameState() })
        break
      case 'game.action':
        if (this.game && !this.game.finished) this.applyAction(payload)
        break
    }
  }

  gameState() {
    const me = this.game.players.find((player) => player.id === this.player.id)
    return { ...this.game, myAdjacentIds: adjacentIds(this.game.board, me.identityId) }
  }

  applyAction(payload) {
    const { board, players } = this.game
    const me = players.find((player) => player.id === this.player.id)
    const target = board.flat().find((card) => card.suspectId === payload.targetId)
    let event
    let message

    switch (payload.action) {
      case 'catch':
      case 'interrogate':
        if (!target?.alive || !adjacentIds(board, me.identityId).includes(target.suspectId)) {
          return this.emit('error', { message: 'Выберите соседнюю живую клетку' })
        }
        if (payload.action === 'interrogate') {
          event = { type: 'interrogate', raisingHands: [{ playerName: players[1].name }] }
          message = `Допрос персонажа «${target.name}»`
          break
        }
        if (target.suspectId === me.identityId) {
          return this.emit('error', { message: 'Нельзя поймать себя' })
        }
        event = { type: 'catch_hit' }
        target.alive = false
        me.trophyCount += 1
        message = `Пойман персонаж «${target.name}»`
        break
      case 'shift':
        event = { type: 'shift' }
        this.game.board = shiftBoard(board, payload)
        message = payload.axis === 'row' ? 'Ряд сдвинут' : 'Столбец сдвинут'
        break
      case 'update':
        event = { type: 'update' }
        board.flat().forEach((card) => { card.alive = true })
        message = 'Поле обновлено'
        break
      default:
        return
    }

    const events = [event]
    this.game.log.push({ message })
    if (me.trophyCount >= this.game.trophiesToWin) {
      this.game.finished = true
      this.game.winnerLabel = me.name
      events.push({ type: 'victory' })
    }
    this.emit('game.updated', { game: this.gameState(), events })
  }
}
