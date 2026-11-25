<template>
  <div class="lobbies rise">
    <header class="page-heading">
      <div>
        <h1 class="page-title">Лобби</h1>
        <p class="sub">Создайте лобби или присоединитесь к сбору игроков.</p>
      </div>
      <div class="account" v-if="state.playerName">
        <span class="avatar" aria-hidden="true">{{ state.playerName.slice(0, 1).toUpperCase() }}</span>
        <div>
          <strong>{{ state.playerName }}</strong>
          <span class="conn" :class="{ on: state.connected }" role="status">
            {{ state.connected ? 'Онлайн' : 'Подключение…' }}
          </span>
        </div>
      </div>
    </header>

    <form class="panel name-panel" v-if="!state.playerName" @submit.prevent="saveName">
      <h2>Ваше имя</h2>
      <div class="field">
        <label for="agent-name">Как вас зовут за столом</label>
        <input id="agent-name" v-model="nameDraft" maxlength="24" placeholder="Агент…" />
      </div>
      <button type="submit" class="btn btn-primary" :disabled="!nameDraft.trim()">Войти</button>
    </form>

    <template v-else>
      <p class="err" role="alert" v-if="state.error">{{ state.error }}</p>

      <div class="grid">
        <section class="panel list">
          <div class="list-heading">
            <h2>Все лобби</h2>
            <button type="button" class="btn" @click="api.refreshLobbies()">Обновить</button>
          </div>
          <ul v-if="state.lobbies.length" class="lobby-list">
            <li v-for="lobby in state.lobbies" :key="lobby.id" class="lobby-row">
              <div class="lobby-copy">
                <div class="lobby-name">{{ lobby.name }}</div>
                <div class="lobby-meta">
                  <span class="status-dot" :class="lobby.status" />
                  {{ lobby.statusLabel }} <span aria-hidden="true">·</span> {{ lobby.playerCount }}/{{ lobby.maxPlayers }}
                </div>
                <div v-if="lobby.artPackLabel" class="lobby-pack">{{ lobby.artPackLabel }}</div>
              </div>
              <button
                type="button"
                class="btn"
                :disabled="!lobby.canJoin"
                @click="join(lobby.id)"
              >
                {{ lobby.canJoin ? 'Войти' : 'Закрыто' }}
              </button>
            </li>
          </ul>
          <p v-else class="empty">Пока нет лобби. Создайте первое, чтобы собрать игроков.</p>
        </section>

        <form class="panel create" @submit.prevent="create">
          <h2>Создать лобби</h2>
          <div class="field">
            <label for="lobby-name">Название</label>
            <input id="lobby-name" v-model="form.name" maxlength="40" placeholder="Ночное агентство" />
          </div>
          <div class="field">
            <label for="lobby-capacity">Игроков</label>
            <select id="lobby-capacity" v-model.number="form.maxPlayers">
              <option v-for="n in [3, 4, 5, 6, 8, 9]" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div class="field">
            <label for="lobby-pack">Набор персонажей</label>
            <select id="lobby-pack" v-model="form.artPack">
              <option
                v-for="pack in artPackOptions"
                :key="pack.id"
                :value="pack.id"
              >
                {{ pack.label }}
              </option>
            </select>
            <p v-if="selectedPackDesc" class="pack-hint">{{ selectedPackDesc }}</p>
          </div>
          <div class="pack-preview" v-if="form.artPack">
            <figure v-for="card in previewCards" :key="card.id" class="pack-preview-item">
              <img
                :src="portraitUrl(form.artPack, card.id)"
                alt=""
                @error="onPortraitError($event, form.artPack, card.id)"
              />
              <figcaption>{{ card.name }}</figcaption>
            </figure>
          </div>
          <button type="submit" class="btn btn-primary">Создать</button>
        </form>
      </div>
    </template>
  </div>
</template>

<script setup>
import { reactive, ref, watch, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useGameSocket } from '../composables/useGameSocket'
import { unlockAudio } from '../composables/sounds'
import { portraitUrl, onPortraitError, PACK_PREVIEWS } from '../composables/artPacks'

const router = useRouter()
const api = useGameSocket()
const { state } = api
const nameDraft = ref('')
const defaultPack = { id: 'brush-flat', label: 'Плоская кисть' }
const form = reactive({ name: 'Лобби', maxPlayers: 4, artPack: defaultPack.id })
const previewCards = computed(() => {
  const pack = artPackOptions.value.find((x) => x.id === form.artPack)
  if (pack?.preview?.length) return pack.preview
  const ids = PACK_PREVIEWS[form.artPack] || PACK_PREVIEWS['ink-minimal']
  return ids.map((id) => ({ id, name: id }))
})

const artPackOptions = computed(() =>
  state.artPacks.length
    ? state.artPacks
    : [defaultPack]
)

const selectedPackDesc = computed(() => {
  const p = artPackOptions.value.find((x) => x.id === form.artPack)
  return p?.description || ''
})

onMounted(() => {
  unlockAudio()
  api.connect()
  nameDraft.value = state.playerName || ''
})

watch(
  () => state.lobby,
  (lobby) => {
    if (lobby && lobby.status === 'gathering') {
      router.push({ name: 'lobby' })
    }
    if (lobby && lobby.status === 'playing' && state.game) {
      router.push({ name: 'game' })
    }
  }
)

watch(
  () => state.artPacks,
  (packs) => {
    if (packs.length && !packs.some((p) => p.id === form.artPack)) {
      form.artPack = packs[0].id
    }
  }
)

function saveName() {
  api.setName(nameDraft.value)
}

function create() {
  api.createLobby(form.name || 'Лобби', form.maxPlayers, form.artPack)
}

function join(id) {
  api.joinLobby(id)
}
</script>

<style scoped>
.page-heading {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: end;
  gap: 1rem 2rem;
  margin-bottom: clamp(1.25rem, 3vw, 2.25rem);
}

.sub { margin: 0; color: var(--fog); }
.name-panel { max-width: 27rem; }
.name-panel .btn, .create > .btn { width: 100%; }

.account {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  max-width: 100%;
  overflow-wrap: anywhere;
}

.avatar {
  display: grid;
  place-items: center;
  flex: 0 0 2.65rem;
  height: 2.65rem;
  border: 1px solid #76564c;
  border-radius: 0.65rem;
  background: #452e26;
  color: var(--brass);
  font-weight: 700;
}

.conn { display: block; color: var(--fog); font-size: 0.8rem; }
.conn.on { color: var(--ok); }

.grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
  gap: clamp(0.8rem, 2vw, 1.35rem);
}

@media (min-width: 760px) {
  .grid { grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr); }
}

.list-heading {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 1.15rem;
}

.list-heading h2 { margin: 0; }
.list-heading .btn { min-height: 2.25rem; padding: 0.35rem 0.65rem; }

.lobby-list { display: grid; gap: 0.65rem; margin: 0; padding: 0; list-style: none; }

.lobby-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.85rem;
  padding: clamp(0.7rem, 2vw, 1.1rem);
  border: 1px solid var(--line);
  border-radius: 0.75rem;
  background: #191716;
}

.lobby-copy { min-width: 0; overflow-wrap: anywhere; }
.lobby-name { font-size: 1.05rem; font-weight: 600; }
.lobby-meta { margin-top: 0.18rem; color: var(--fog); font-size: 0.85rem; }
.lobby-pack { margin-top: 0.1rem; color: var(--fog); font-size: 0.76rem; }
.lobby-row .btn:not(:disabled) { color: var(--brass); }

.empty {
  padding: 1rem;
  margin: 0;
  border: 1px dashed var(--line);
  border-radius: 0.75rem;
  color: var(--fog);
}

.pack-hint { margin: 0; color: var(--fog); font-size: 0.78rem; }

.pack-preview {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.45rem;
  margin: 0 0 1.2rem;
}

.pack-preview-item { margin: 0; min-width: 0; }

.pack-preview-item img {
  display: block;
  width: 100%;
  aspect-ratio: 4 / 5;
  object-fit: cover;
  border: 1px solid var(--line);
  border-radius: 0.5rem;
  background: var(--ink);
}

.pack-preview-item figcaption {
  margin-top: 0.28rem;
  color: var(--fog);
  font-size: 0.72rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
