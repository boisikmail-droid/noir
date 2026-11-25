import { reactive } from 'vue'

const prefs = reactive({
  muted: localStorage.getItem('noir_mute') === '1',
})

let ctx = null
let unlocked = false

function getCtx() {
  if (!ctx) {
    ctx = new (window.AudioContext || window.webkitAudioContext)()
  }
  return ctx
}

/** Браузеры блокируют звук до жеста — вызываем на первом клике. */
export function unlockAudio() {
  if (unlocked) return
  const c = getCtx()
  if (c.state === 'suspended') {
    c.resume()
  }
  // тихий клик, чтобы «разбудить» контекст
  const o = c.createOscillator()
  const g = c.createGain()
  g.gain.value = 0.0001
  o.connect(g)
  g.connect(c.destination)
  o.start()
  o.stop(c.currentTime + 0.01)
  unlocked = true
}

export function setMuted(v) {
  prefs.muted = v
  localStorage.setItem('noir_mute', v ? '1' : '0')
}

export function isMuted() {
  return prefs.muted
}

export function useSoundPrefs() {
  return prefs
}

function tone(freq, dur, type = 'sine', gain = 0.08, when = 0) {
  if (prefs.muted) return
  const c = getCtx()
  const t0 = c.currentTime + when
  const o = c.createOscillator()
  const g = c.createGain()
  o.type = type
  o.frequency.setValueAtTime(freq, t0)
  g.gain.setValueAtTime(0.0001, t0)
  g.gain.exponentialRampToValueAtTime(gain, t0 + 0.02)
  g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur)
  o.connect(g)
  g.connect(c.destination)
  o.start(t0)
  o.stop(t0 + dur + 0.02)
}

function noiseBurst(dur, gain = 0.05, when = 0) {
  if (prefs.muted) return
  const c = getCtx()
  const t0 = c.currentTime + when
  const len = Math.floor(c.sampleRate * dur)
  const buf = c.createBuffer(1, len, c.sampleRate)
  const data = buf.getChannelData(0)
  for (let i = 0; i < len; i++) {
    data[i] = (Math.random() * 2 - 1) * (1 - i / len)
  }
  const src = c.createBufferSource()
  src.buffer = buf
  const g = c.createGain()
  const f = c.createBiquadFilter()
  f.type = 'bandpass'
  f.frequency.value = 1200
  g.gain.value = gain
  src.connect(f)
  f.connect(g)
  g.connect(c.destination)
  src.start(t0)
}

/** Карта событий → характерные мотивы (слышно всем клиентам). */
const cues = {
  connect: () => {
    tone(220, 0.08, 'triangle', 0.04)
    tone(330, 0.1, 'triangle', 0.04, 0.08)
  },
  lobby_join: () => {
    tone(392, 0.12, 'sine', 0.06)
    tone(523, 0.14, 'sine', 0.05, 0.1)
  },
  lobby_leave: () => {
    tone(349, 0.1, 'sine', 0.04)
    tone(262, 0.14, 'sine', 0.04, 0.09)
  },
  game_start: () => {
    tone(196, 0.15, 'sawtooth', 0.04)
    tone(247, 0.15, 'sawtooth', 0.045, 0.12)
    tone(294, 0.2, 'sawtooth', 0.05, 0.24)
    tone(392, 0.35, 'triangle', 0.06, 0.4)
  },
  catch_hit: () => {
    noiseBurst(0.12, 0.07)
    tone(160, 0.2, 'square', 0.07, 0.02)
    tone(120, 0.35, 'square', 0.05, 0.12)
  },
  catch_miss: () => {
    tone(300, 0.08, 'triangle', 0.04)
    tone(240, 0.18, 'triangle', 0.035, 0.1)
  },
  interrogate: () => {
    tone(880, 0.06, 'sine', 0.035)
    tone(660, 0.06, 'sine', 0.03, 0.08)
    tone(990, 0.06, 'sine', 0.035, 0.16)
    tone(740, 0.2, 'sine', 0.04, 0.24)
  },
  shift: () => {
    noiseBurst(0.08, 0.04)
    tone(180, 0.1, 'sawtooth', 0.035, 0.02)
    tone(220, 0.12, 'sawtooth', 0.03, 0.1)
  },
  update: () => {
    tone(150, 0.1, 'triangle', 0.05)
    tone(200, 0.1, 'triangle', 0.05, 0.1)
    tone(250, 0.1, 'triangle', 0.05, 0.2)
    tone(300, 0.25, 'triangle', 0.06, 0.3)
  },
  your_turn: () => {
    tone(523, 0.1, 'sine', 0.055)
    tone(659, 0.18, 'sine', 0.06, 0.1)
  },
  victory: () => {
    ;[392, 494, 587, 784].forEach((f, i) => tone(f, 0.28, 'triangle', 0.07, i * 0.14))
  },
  error: () => {
    tone(140, 0.2, 'square', 0.05)
    tone(110, 0.25, 'square', 0.04, 0.12)
  },
  select: () => tone(440, 0.04, 'sine', 0.025),
}

export function playCue(name) {
  unlockAudio()
  const fn = cues[name]
  if (fn) fn()
}

/**
 * Реакция на WS-сообщения — вызывается у каждого игрока.
 * @param {object} data
 * @param {{ playerId: string, prevCurrentPlayerId?: string|null }} ctxInfo
 */
export function playForMessage(data, ctxInfo = {}) {
  const { playerId, prevCurrentPlayerId } = ctxInfo
  switch (data.type) {
    case 'hello.ok':
      playCue('connect')
      break
    case 'lobby.joined':
      playCue('lobby_join')
      break
    case 'lobby.updated':
      playCue('select')
      break
    case 'lobby.left':
      playCue('lobby_leave')
      break
    case 'game.started':
      playCue('game_start')
      if (data.payload?.game?.currentPlayerId === playerId) {
        setTimeout(() => playCue('your_turn'), 700)
      }
      break
    case 'game.updated': {
      const events = data.payload?.events || []
      for (const ev of events) {
        if (ev.type === 'catch_hit') playCue('catch_hit')
        else if (ev.type === 'catch_miss') playCue('catch_miss')
        else if (ev.type === 'interrogate') playCue('interrogate')
        else if (ev.type === 'shift') playCue('shift')
        else if (ev.type === 'update') playCue('update')
        else if (ev.type === 'victory') playCue('victory')
      }
      const game = data.payload?.game
      if (game?.finished && !events.some((e) => e.type === 'victory')) {
        playCue('victory')
      } else if (game && !game.finished) {
        const cur = game.currentPlayerId
        if (cur === playerId && prevCurrentPlayerId !== playerId) {
          setTimeout(() => playCue('your_turn'), 200)
        }
      }
      break
    }
    case 'error':
      playCue('error')
      break
    default:
      break
  }
}
