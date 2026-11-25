const WebSocket = require('ws')
const ws = new WebSocket(process.env.WS_URL || 'ws://websocket:8081')
ws.on('open', () => ws.send(JSON.stringify({type:'hello',payload:{playerId:'t',playerName:'T'}})))
ws.on('message', (d) => {
  const m = JSON.parse(d)
  if (m.type === 'hello.ok') ws.send(JSON.stringify({type:'artpacks.list'}))
  if (m.type === 'artpacks.list') {
    for (const p of m.payload.packs) {
      console.log(p.id, '->', (p.preview||[]).map(x=>x.name).join(', '))
    }
    process.exit(0)
  }
  if (m.type === 'error') { console.error(m); process.exit(1) }
})
setTimeout(() => process.exit(2), 5000)
