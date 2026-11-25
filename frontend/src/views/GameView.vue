<template>
  <div class="game rise" v-if="game">
    <header class="game-top">
      <div>
        <h1 class="page-title">Игровое поле</h1>
        <p class="meta">
          Поле {{ game.size }}×{{ game.size }} · до {{ game.trophiesToWin }} трофеев
          <span v-if="game.teamMode"> · командный режим</span>
        </p>
      </div>
      <div class="identity panel" v-if="me">
        <img
          v-if="me.identityId"
          class="id-art"
          :src="portraitUrl(game.artPack, me.identityId)"
          alt=""
          @error="onPortraitError($event, game.artPack, me.identityId)"
        />
        <div class="identity-copy">
          <p class="eyebrow">Ваша личность</p>
          <div class="id-name">{{ me.identityName || '—' }}</div>
        </div>
        <div class="trophies"><strong>{{ me.trophyCount }}</strong><span>Трофеи</span></div>
      </div>
    </header>

    <div class="victory panel" role="status" v-if="game.finished">
      Победа: <strong>{{ game.winnerLabel }}</strong>
    </div>

    <div class="layout">
      <div class="board-wrap">
        <div class="board-heading">
          <p class="turn" :class="{ mine: isMyTurn }" v-if="!game.finished" role="status">
            <span class="status-dot" aria-hidden="true" />
            <strong v-if="isMyTurn">Ваш ход</strong>
            <template v-else>Ход: <strong>{{ currentName }}</strong></template>
          </p>
          <div class="board-legend" aria-label="Обозначения поля">
            <span><i class="legend-you" aria-hidden="true" />Ваша карта</span>
            <span><i class="legend-adjacent" aria-hidden="true" />Соседние клетки</span>
          </div>
        </div>
        <div
          class="board"
          :class="{ 'board-dense': game.size === 7 }"
          :style="{ gridTemplateColumns: `repeat(${game.size}, minmax(0, 1fr))` }"
        >
          <button
            v-for="cell in flatCells"
            :key="cell.suspectId + '-' + cell.row + '-' + cell.col"
            class="cell"
            :class="{
              dead: !cell.alive,
              mine: cell.suspectId === me?.identityId,
              adj: isAdjacent(cell.suspectId),
              selected: selectedId === cell.suspectId,
            }"
            :disabled="!cell.alive || game.finished"
            :aria-pressed="selectedId === cell.suspectId"
            :title="cell.name"
            type="button"
            @click="select(cell)"
          >
            <img
              class="cell-art"
              :src="portraitUrl(game.artPack, cell.suspectId)"
              alt=""
              draggable="false"
              @error="onPortraitError($event, game.artPack, cell.suspectId)"
            />
            <span class="cell-name">{{ cell.name }}</span>
            <span v-if="!cell.alive" class="cell-state" aria-label="Выбыл">×</span>
          </button>
        </div>
      </div>

      <aside class="side">
        <div class="panel actions">
          <h2>Действие</h2>
          <p class="err" role="alert" v-if="state.error">{{ state.error }}</p>
          <p class="hands" role="status" v-if="state.raisingHands">
            <strong>Допрос — руку подняли:</strong> {{ handNames }}
          </p>
          <p class="hint target" role="status">
            <template v-if="selectedCell">Цель: <strong>{{ selectedCell.name }}</strong></template>
            <template v-else>Выберите клетку на поле</template>
          </p>
          <div class="action-pair">
            <button class="btn btn-primary" :disabled="!canAct || !selectedId" @click="doCatch">Поймать</button>
            <button class="btn" :disabled="!canAct || !selectedId" @click="doInterrogate">Допросить</button>
          </div>

          <h3>Сдвиг</h3>
          <div class="shift-row">
            <select class="control" aria-label="Ряд или столбец" v-model="shift.axis">
              <option value="row">Ряд</option>
              <option value="col">Столбец</option>
            </select>
            <select class="control" aria-label="Номер ряда или столбца" v-model.number="shift.index">
              <option v-for="i in game.size" :key="i" :value="i - 1">№ {{ i }}</option>
            </select>
            <select class="control" aria-label="Направление сдвига" v-model="shift.direction">
              <option value="forward">{{ shift.axis === 'row' ? 'Вправо' : 'Вниз' }}</option>
              <option value="backward">{{ shift.axis === 'row' ? 'Влево' : 'Вверх' }}</option>
            </select>
          </div>
          <div class="action-pair">
            <button class="btn" :disabled="!canAct" @click="doShift">Сдвинуть</button>
            <button class="btn" :disabled="!canAct || !game.canUpdate" @click="doUpdate">Обновить поле</button>
          </div>
        </div>

        <div class="panel players">
          <h2>Шпионы</h2>
          <ul>
            <li v-for="p in game.players" :key="p.id" :class="{ active: p.id === game.currentPlayerId }">
              <span>{{ p.name }}</span>
              <span v-if="p.team !== null && p.team !== undefined" class="team">К{{ p.team + 1 }}</span>
              <span class="cup">{{ p.trophyCount }}★</span>
            </li>
          </ul>
        </div>

        <div class="panel log">
          <h2>Журнал</h2>
          <ul>
            <li v-for="(entry, i) in [...game.log].reverse()" :key="i">{{ entry.message }}</li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
  <div v-else class="panel">
    <p>Игра не загружена.</p>
    <router-link class="btn" to="/spy-games">К лобби</router-link>
  </div>
</template>

<script setup>
import { computed, reactive, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useGameSocket } from '../composables/useGameSocket'
import { portraitUrl, onPortraitError } from '../composables/artPacks'
import { playCue, unlockAudio } from '../composables/sounds'

const router = useRouter()
const api = useGameSocket()
const { state } = api

const selectedId = ref(null)
const shift = reactive({ axis: 'row', index: 0, direction: 'forward' })

const game = computed(() => state.game)
const me = computed(() => game.value?.players?.find((p) => p.id === state.playerId))
const isMyTurn = computed(() => game.value?.currentPlayerId === state.playerId)
const canAct = computed(() => isMyTurn.value && !game.value?.finished)

const currentName = computed(() => {
  const id = game.value?.currentPlayerId
  return game.value?.players?.find((p) => p.id === id)?.name || '—'
})

const flatCells = computed(() => {
  if (!game.value) return []
  const out = []
  game.value.board.forEach((row, r) => {
    row.forEach((cell, c) => {
      out.push({ ...cell, row: r, col: c })
    })
  })
  return out
})

const selectedCell = computed(() =>
  flatCells.value.find((c) => c.suspectId === selectedId.value)
)

const handNames = computed(() =>
  (state.raisingHands || []).map((h) => h.playerName).join(', ') || 'никто'
)

function isAdjacent(id) {
  return (game.value?.myAdjacentIds || []).includes(id)
}

function select(cell) {
  if (!cell.alive) return
  unlockAudio()
  playCue('select')
  selectedId.value = cell.suspectId
}

function doCatch() {
  api.gameAction({ action: 'catch', targetId: selectedId.value })
}
function doInterrogate() {
  api.gameAction({ action: 'interrogate', targetId: selectedId.value })
}
function doShift() {
  api.gameAction({
    action: 'shift',
    axis: shift.axis,
    index: shift.index,
    direction: shift.direction,
  })
}
function doUpdate() {
  api.gameAction({ action: 'update' })
}

onMounted(() => {
  api.connect()
  if (!state.game) {
    api.send('game.state')
    setTimeout(() => {
      if (!state.game) router.replace({ name: 'lobbies' })
    }, 800)
  }
})

watch(
  () => game.value?.size,
  (s) => {
    if (s && shift.index >= s) shift.index = 0
  }
)
</script>

<style scoped>
.game {
  --game-columns: minmax(0, 1.64fr) minmax(19rem, 1fr);
  --game-gap: clamp(0.75rem, 2vw, 1.3rem);
}

.game-top, .layout {
  display: grid;
  grid-template-columns: var(--game-columns);
  gap: var(--game-gap);
}

.game-top {
  align-items: end;
  margin-bottom: clamp(0.85rem, 2vw, 1.5rem);
}

.game-top .page-title { font-size: clamp(2.65rem, 4.9vw, 4.4rem); }
.meta { margin: 0; color: var(--fog); font-size: 0.9rem; }

.identity {
  display: flex;
  align-items: center;
  gap: clamp(0.6rem, 2vw, 0.9rem);
  padding: 0.65rem;
}

.identity-copy { min-width: 0; }

.id-art {
  flex: 0 0 auto;
  width: 3.4rem;
  height: 3.65rem;
  object-fit: cover;
  object-position: top;
  border-radius: 0.55rem;
  border: 1px solid var(--line);
}

.id-name { font-size: clamp(1.05rem, 2vw, 1.28rem); font-weight: 600; overflow-wrap: anywhere; }

.trophies {
  display: grid;
  justify-items: center;
  gap: 0.1rem;
  margin-left: auto;
  padding-inline: 0.6rem;
  color: var(--fog);
  font-size: 0.76rem;
}

.trophies strong { color: var(--brass); font-size: 1.25rem; line-height: 1; }

.victory {
  position: sticky;
  z-index: 2;
  top: var(--safe-top, 0px);
  margin-bottom: var(--game-gap);
  padding-block: 0.8rem;
  background: #432b24;
  border-color: #a16b59;
  font-size: 1.08rem;
  color: #ffd2bf;
}

.hands {
  margin: 0 0 0.7rem;
  padding: 0.6rem 0.7rem;
  border: 1px solid #536d5d;
  border-radius: 0.55rem;
  background: #26312a;
  color: #cce9d3;
  font-size: 0.9rem;
}

.layout { align-items: start; }

.board-wrap {
  min-width: 0;
  border: 1px solid var(--line);
  border-radius: var(--radius);
  background: #1d1a18;
  padding: clamp(0.4rem, 1.8vw, 1rem);
}

.board-heading {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: 0.55rem 1rem;
  min-height: 2rem;
  margin: 0 0 0.7rem;
  padding-inline: 0.2rem;
}

.turn { margin: 0; font-size: 0.9rem; }
.turn.mine { color: var(--ok); }
.turn.mine .status-dot { background: var(--ok); }

.board-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem 0.75rem;
  color: var(--fog);
  font-size: 0.73rem;
}

.board-legend span { display: flex; align-items: center; gap: 0.35rem; }

.board-legend i {
  width: 0.65rem;
  height: 0.65rem;
  border: 1px solid var(--brass);
  border-radius: 0.15rem;
}

.board-legend .legend-adjacent { border-color: #c9a383; border-style: dashed; }

.board { display: grid; gap: clamp(0.18rem, 0.7vw, 0.6rem); }

.cell {
  position: relative;
  isolation: isolate;
  display: flex;
  align-items: end;
  justify-content: center;
  width: 100%;
  min-width: 0;
  padding: 0;
  aspect-ratio: 1 / 1.03;
  overflow: hidden;
  border: 1px solid #554a43;
  border-radius: clamp(0.3rem, 1vw, 0.65rem);
  background: #302523;
  color: var(--paper);
  cursor: pointer;
}

.cell-art {
  position: absolute;
  inset: 0;
  z-index: -2;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 28%;
  pointer-events: none;
}

.cell::before {
  content: '';
  position: absolute;
  inset: 35% 0 0;
  z-index: -1;
  background: linear-gradient(transparent, rgb(16 12 11 / 95%));
}

.cell-name {
  display: block;
  width: 100%;
  padding: 0.7rem 0.16rem 0.25rem;
  font-size: clamp(0.6rem, 1.5vw, 0.8rem);
  font-weight: 600;
  line-height: 1.08;
  text-align: center;
  text-shadow: 0 1px 3px black;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.cell.adj { border-style: dashed; border-color: #c9a383; }

.cell.mine {
  border-style: solid;
  border-color: var(--brass);
  box-shadow: inset 0 0 0 1px var(--brass);
}

.cell:hover:not(:disabled), .cell.selected {
  border-style: solid;
  border-color: #fff6e8;
  box-shadow: inset 0 0 0 1px #fff6e8;
}

.cell:disabled { cursor: default; }
.cell.dead { opacity: 0.52; }
.cell.dead .cell-art { filter: grayscale(1) brightness(0.75); }

.cell-state {
  position: absolute;
  top: 0.12rem;
  right: 0.23rem;
  line-height: 1;
  font-size: 1.2rem;
  color: #ffb0a9;
  text-shadow: 0 1px 5px black;
}

.side { display: grid; gap: 0.8rem; min-width: 0; }
.side .panel { padding: clamp(0.8rem, 1.7vw, 1.25rem); }
.actions h2 { margin-bottom: 0.55rem; }
.actions h3 { margin: 0.95rem 0 0.45rem; font-size: 0.86rem; font-weight: 600; }
.target { margin: 0 0 0.7rem; color: var(--fog); font-size: 0.9rem; }
.target strong { color: var(--paper); }

.action-pair, .shift-row { display: grid; gap: 0.4rem; }
.action-pair { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.action-pair .btn { padding-inline: 0.38rem; }
.shift-row { grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.75fr) minmax(0, 1fr); margin-bottom: 0.5rem; }
.shift-row select { font-size: 0.82rem; padding-inline: 0.3rem; }

.players ul, .log ul { margin: 0; padding: 0; list-style: none; }
.players li, .log li { padding: 0.42rem 0; border-top: 1px solid var(--line); font-size: 0.85rem; }
.players li { display: flex; gap: 0.5rem; align-items: center; overflow-wrap: anywhere; }
.players li.active { color: var(--ok); }
.team { color: var(--fog); font-size: 0.75rem; }
.cup { margin-left: auto; white-space: nowrap; color: var(--brass); }
.log ul { max-height: 11rem; overflow: auto; color: var(--fog); }

@media (max-width: 820px) {
  .game { --game-columns: minmax(0, 1fr); }
}

@media (min-width: 821px) {
  .cell { aspect-ratio: 1.2 / 1; }
}

@media (max-width: 500px) {
  .board-heading { margin-bottom: 0.45rem; }
  .board-dense .cell-name { font-size: 0.54rem; }
  .game-top .page-title { font-size: clamp(2rem, 9vw, 2.8rem); }
}
</style>
