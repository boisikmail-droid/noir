const WebSocket = require('ws')

async function once(ws, type) {
  return new Promise((resolve, reject) => {
    const t = setTimeout(() => reject(new Error('timeout ' + type)), 4000)
    ws.on('message', function handler(d) {
      const m = JSON.parse(d.toString())
      if (m.type === type || (type === 'any_error' && m.type === 'error')) {
        clearTimeout(t)
        ws.off('message', handler)
        resolve(m)
      }
    })
  })
}

function client(id, name) {
  const ws = new WebSocket(process.env.WS_URL || 'ws://websocket:8081')
  const ready = new Promise((resolve, reject) => {
    ws.on('open', resolve)
    ws.on('error', reject)
  })
  return { ws, ready, id, name }
}

;(async () => {
  const a = client('a1', 'Host')
  const b = client('b1', 'Guest')
  const c = client('c1', 'Late')
  await Promise.all([a.ready, b.ready, c.ready])

  a.ws.send(JSON.stringify({ type: 'hello', payload: { playerId: a.id, playerName: a.name } }))
  await once(a.ws, 'lobby.list')
  a.ws.send(JSON.stringify({ type: 'lobby.create', payload: { name: 'Full', maxPlayers: 3, playerName: a.name } }))
  const joined = await once(a.ws, 'lobby.joined')
  const lobbyId = joined.payload.lobby.id

  b.ws.send(JSON.stringify({ type: 'hello', payload: { playerId: b.id, playerName: b.name } }))
  await once(b.ws, 'lobby.list')
  // need third for start - create more
  const d = client('d1', 'Third')
  await d.ready
  d.ws.send(JSON.stringify({ type: 'hello', payload: { playerId: d.id, playerName: d.name } }))
  await once(d.ws, 'lobby.list')

  b.ws.send(JSON.stringify({ type: 'lobby.join', payload: { lobbyId, playerName: b.name } }))
  await once(b.ws, 'lobby.joined')
  d.ws.send(JSON.stringify({ type: 'lobby.join', payload: { lobbyId, playerName: d.name } }))
  await once(d.ws, 'lobby.joined')

  a.ws.send(JSON.stringify({ type: 'lobby.start', payload: {} }))
  const started = await once(a.ws, 'game.started')
  console.log('GAME_STARTED size', started.payload.game.size, 'players', started.payload.game.players.length)

  c.ws.send(JSON.stringify({ type: 'hello', payload: { playerId: c.id, playerName: c.name } }))
  await once(c.ws, 'lobby.list')
  c.ws.send(JSON.stringify({ type: 'lobby.join', payload: { lobbyId, playerName: c.name } }))
  const err = await once(c.ws, 'error')
  console.log('JOIN_BLOCKED', err.payload.message)
  process.exit(0)
})().catch((e) => {
  console.error(e)
  process.exit(1)
})
