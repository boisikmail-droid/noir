<template>
  <div class="room rise" v-if="state.lobby">
    <header class="room-header">
      <p class="eyebrow"><span class="status-dot" :class="state.lobby.status" />{{ state.lobby.statusLabel }}</p>
      <h1 class="page-title">{{ state.lobby.name }}</h1>
      <p class="room-pack" v-if="state.lobby.artPackLabel">{{ state.lobby.artPackLabel }}</p>
    </header>

    <p class="err" role="alert" v-if="state.error">{{ state.error }}</p>

    <div class="room-grid">
      <section class="panel">
        <h2>Игроки</h2>
        <ul class="players">
          <li v-for="p in state.lobby.players" :key="p.id" :class="{ 'is-you': p.id === state.playerId }">
            <span class="avatar" aria-hidden="true">{{ p.name.slice(0, 1).toUpperCase() }}</span>
            <div>
              <strong>{{ p.name }}</strong>
              <span class="player-role" v-if="p.isHost || p.id === state.playerId">
                {{ p.isHost ? 'Хост' : '' }}{{ p.isHost && p.id === state.playerId ? ' · ' : '' }}{{ p.id === state.playerId ? 'Вы' : '' }}
              </span>
            </div>
          </li>
        </ul>
      </section>

      <section class="panel room-actions">
        <h2>Места за столом</h2>
        <p class="seat-count">{{ state.lobby.playerCount }} <span>/ {{ state.lobby.maxPlayers }}</span></p>
        <progress aria-label="Занятые места" :value="state.lobby.playerCount" :max="state.lobby.maxPlayers" />
        <p v-if="!isHost" class="hint" role="status">Ждём, пока хост начнёт игру…</p>
        <p v-else-if="!canStart" class="hint">Нужно 3–6, 8 или 9 игроков.</p>
        <button v-if="isHost" type="button" class="btn btn-primary" :disabled="!canStart" @click="api.startLobby()">
          Начать игру <span aria-hidden="true">↗</span>
        </button>
        <button type="button" class="btn btn-danger" @click="leave">Выйти</button>
      </section>
    </div>
  </div>
  <div v-else class="panel">
    <p>Лобби не найдено.</p>
    <router-link class="btn" to="/spy-games">К списку</router-link>
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useGameSocket } from '../composables/useGameSocket'

const router = useRouter()
const api = useGameSocket()
const { state } = api

const isHost = computed(
  () => state.lobby && state.lobby.hostPlayerId === state.playerId
)
const canStart = computed(() => {
  const n = state.lobby?.playerCount || 0
  return [3, 4, 5, 6, 8, 9].includes(n)
})

onMounted(() => {
  api.connect()
  if (!state.lobby) {
    router.replace({ name: 'lobbies' })
  }
})

watch(
  () => state.game,
  (game) => {
    if (game) router.push({ name: 'game' })
  }
)

function leave() {
  api.leaveLobby()
  router.push({ name: 'lobbies' })
}
</script>

<style scoped>
.room-header { margin-bottom: clamp(1.2rem, 3vw, 2.2rem); }
.room-pack { margin: 0; color: var(--fog); }

.room-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
  gap: clamp(0.8rem, 2vw, 1.35rem);
}

@media (min-width: 760px) {
  .room-grid { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); }
}

.players {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.players li {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  min-height: 4rem;
  padding: 0.6rem;
  border: 1px solid var(--line);
  border-radius: 0.7rem;
  background: #191716;
  overflow-wrap: anywhere;
}

.players li.is-you { border-color: #866054; background: #2b211e; }

.avatar {
  display: grid;
  place-items: center;
  flex: 0 0 2.5rem;
  height: 2.5rem;
  border-radius: 0.55rem;
  background: var(--slate);
  color: var(--brass);
  font-weight: 700;
}

.player-role { display: block; color: var(--fog); font-size: 0.8rem; }

.room-actions { display: grid; gap: 0.65rem; }
.room-actions h2 { margin: 0; }

.seat-count {
  margin: 0;
  line-height: 1.15;
  font-family: var(--font-display);
  color: var(--paper);
  font-size: 2.4rem;
}

.seat-count span { color: var(--fog); font-size: 1.25rem; }

progress {
  display: block;
  width: 100%;
  height: 0.5rem;
  margin: 0.2rem 0 0.65rem;
  accent-color: var(--brass);
}

.hint { margin: 0 0 0.45rem; color: var(--fog); font-size: 0.9rem; }

@media (max-width: 460px) {
  .players li { gap: 0.45rem; min-height: 3rem; padding: 0.45rem; font-size: 0.9rem; }
  .avatar { flex-basis: 1.8rem; height: 1.8rem; }
}
</style>
