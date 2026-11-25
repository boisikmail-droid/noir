const { WebSocket } = require('ws')

const ws = new WebSocket(process.env.WS_URL || 'ws://websocket:8081')

ws.on('open', () => {
  ws.send(JSON.stringify({
    type: 'hello',
    payload: { playerId: 't1', playerName: 'Test' },
  }))
})

ws.on('message', (d) => {
  const m = JSON.parse(d.toString())
  console.log('MSG', m.type)
  if (m.type === 'lobby.list') {
    ws.send(JSON.stringify({
      type: 'lobby.create',
      payload: { name: 'Demo', maxPlayers: 4, playerName: 'Test' },
    }))
  }
  if (m.type === 'lobby.joined') {
    console.log('LOBBY_OK', m.payload.lobby.status, m.payload.lobby.playerCount)
    process.exit(0)
  }
  if (m.type === 'error') {
    console.error(m)
    process.exit(1)
  }
})

ws.on('error', (e) => {
  console.error(e)
  process.exit(1)
})

setTimeout(() => process.exit(2), 5000)
