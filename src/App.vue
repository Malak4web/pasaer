<script setup>
import AppSidebar from './components/AppSidebar.vue'
import TopBar from './components/TopBar.vue'
import MobileNav from './components/MobileNav.vue'
import AudioPlayer from './components/AudioPlayer.vue'
import { usePlayerStore } from './stores/player'
import { useSiteStore } from './stores/site'

const player = usePlayerStore()
useSiteStore().load()
</script>

<template>
  <a href="#main" class="skip">تخطَّ إلى المحتوى</a>
  <div class="shell" :class="{ 'has-player': player.track }">
    <AppSidebar />
    <div class="main-col">
      <TopBar />
      <main id="main">
        <RouterView v-slot="{ Component, route }">
          <Transition name="fade" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
          </Transition>
        </RouterView>
      </main>
    </div>
    <AudioPlayer />
    <MobileNav />
  </div>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  display: flex;
}
.main-col {
  flex: 1;
  min-width: 0;
  margin-inline-start: var(--sidebar-w);
}
main { padding-bottom: 24px; }
.has-player main { padding-bottom: calc(var(--player-h) + 24px); }

.skip {
  position: absolute;
  inset-inline-start: 12px;
  top: -60px;
  z-index: 100;
  background: var(--primary);
  color: var(--on-primary);
  padding: 8px 14px;
  border-radius: 8px;
}
.skip:focus { top: 12px; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.15s var(--ease), transform 0.15s var(--ease); }
.fade-enter-from { opacity: 0; transform: translateY(4px); }
.fade-leave-to { opacity: 0; }

@media (max-width: 900px) {
  .main-col { margin-inline-start: 0; }
  main { padding-bottom: calc(var(--mobile-nav-h) + 16px); }
  .has-player main { padding-bottom: calc(var(--mobile-nav-h) + var(--player-h) + 16px); }
}
</style>
