import { createRouter, createWebHistory } from 'vue-router'
import HomeView from './views/HomeView.vue'
import LobbyListView from './views/LobbyListView.vue'
import LobbyRoomView from './views/LobbyRoomView.vue'
import GameView from './views/GameView.vue'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, savedPosition) {
    return savedPosition || { top: 0 }
  },
  routes: [
    { path: '/', name: 'home', component: HomeView },
    { path: '/spy-games', name: 'lobbies', component: LobbyListView },
    { path: '/spy-games/lobby', name: 'lobby', component: LobbyRoomView },
    { path: '/spy-games/game', name: 'game', component: GameView },
  ],
})

export default router
