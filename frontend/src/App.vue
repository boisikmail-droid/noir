<template>
  <div class="app-shell" @pointerdown="onFirstGesture">
    <header class="topbar">
      <div class="topbar-inner">
        <router-link to="/" class="brand"><span class="brand-mark" aria-hidden="true"></span>NOIR</router-link>
        <span class="mode-tag">{{ modeLabel }}</span>
        <button
          type="button"
          class="btn mute-btn"
          aria-label="Звук"
          :aria-pressed="!prefs.muted"
          :title="prefs.muted ? 'Включить звук' : 'Выключить звук'"
          @click="toggleMute"
        >
          Звук <span>{{ prefs.muted ? 'выкл' : 'вкл' }}</span>
        </button>
      </div>
    </header>
    <main class="main" :class="{ 'main-home': route.name === 'home', 'main-game': route.name === 'game' }">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { unlockAudio, setMuted, useSoundPrefs, playCue } from './composables/sounds'
import { isUiTest } from './uiTest'

const route = useRoute()
const prefs = useSoundPrefs()
const modeLabel = computed(() =>
  isUiTest ? 'Тест интерфейса' : (route.name === 'home' ? '' : 'Шпионские игры')
)

function onFirstGesture() {
  unlockAudio()
}

function toggleMute() {
  unlockAudio()
  setMuted(!prefs.muted)
  if (!prefs.muted) playCue('select')
}
</script>

<style scoped>
.mute-btn {
  margin-left: auto;
  min-height: 2.25rem;
  padding: 0.35rem 0.7rem;
  white-space: nowrap;
  font-size: 0.8rem;
}

.mute-btn span {
  margin-left: 0.3em;
  color: var(--fog);
}
</style>
