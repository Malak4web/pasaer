<script setup>
import { ref, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSiteStore } from '@/stores/site'
import { usePlayerStore } from '@/stores/player'
import { RADIO_STATIONS } from '@/data/muslimTools'
import BrandMark from './BrandMark.vue'

const route = useRoute()
const router = useRouter()
const store = useSiteStore()
const player = usePlayerStore()

const q = ref(route.query.q || '')
const mobileSearchOpen = ref(false)
const searchInput = ref(null)

watch(() => route.query.q, (v) => (q.value = v || ''))

async function openMobileSearch() {
  mobileSearchOpen.value = true
  await nextTick()
  searchInput.value?.focus()
}

function closeMobileSearch() {
  mobileSearchOpen.value = false
}

function submit() {
  const term = q.value.trim()
  if (term) {
    mobileSearchOpen.value = false
    router.push({ name: 'search', query: { q: term } })
  }
}

function toggleCairoRadio() {
  if (player.isLive && player.playing) {
    player.pause()
  } else {
    player.play(RADIO_STATIONS[0])
  }
}
</script>

<template>
  <header class="topbar" :class="{ 'search-open': mobileSearchOpen }">
    <RouterLink to="/" class="m-brand">
      <BrandMark />
      <span>{{ store.site.name }}</span>
    </RouterLink>

    <form class="search" role="search" @submit.prevent="submit">
      <i class="ti ti-search" aria-hidden="true"></i>
      <label for="q" class="sr-only">ابحث في المحتوى</label>
      <input
        id="q"
        ref="searchInput"
        v-model="q"
        type="search"
        placeholder="ابحث عن مقطع، درس، أو موضوع…"
        autocomplete="off"
      />
      <button v-if="mobileSearchOpen" type="button" class="m-close" aria-label="إغلاق البحث" @click="closeMobileSearch">
        <i class="ti ti-x" aria-hidden="true"></i>
      </button>
    </form>

    <div class="m-actions">
      <button
        type="button"
        class="m-icon-btn radio-btn"
        :class="{ live: player.isLive && player.playing }"
        :aria-label="player.isLive && player.playing ? 'إيقاف إذاعة القرآن' : 'تشغيل إذاعة القرآن الكريم'"
        @click="toggleCairoRadio"
      >
        <i class="ti ti-radio" aria-hidden="true"></i>
        <span v-if="player.isLive && player.playing" class="dot"></span>
      </button>
      <button type="button" class="m-icon-btn" aria-label="بحث" @click="openMobileSearch">
        <i class="ti ti-search" aria-hidden="true"></i>
      </button>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 15;
  background: color-mix(in srgb, var(--bg) 86%, transparent);
  backdrop-filter: saturate(1.6) blur(14px);
  -webkit-backdrop-filter: saturate(1.6) blur(14px);
  border-bottom: 1px solid var(--border);
  padding: 12px 32px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.m-brand {
  display: none;
  flex-shrink: 0;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 20px;
  color: var(--primary);
}
.m-brand :deep(.mark) { width: 30px; height: 30px; }
.search {
  flex: 1;
  min-width: 0;
  max-width: 520px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 42px;
  padding: 0 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 999px;
  transition: border-color 0.15s var(--ease), background 0.15s var(--ease);
}
.search:focus-within { border-color: var(--primary); background: var(--bg); }
.search .ti { font-size: 19px; color: var(--text-3); }
.search input {
  flex: 1;
  min-width: 0;
  width: 100%;
  border: 0;
  background: none;
  outline: none;
  font-size: 15px;
}
.search input::placeholder { color: var(--text-3); }
.m-close {
  display: none;
  width: 30px; height: 30px; border-radius: 50%;
  place-items: center; color: var(--text-2);
}
.m-actions { display: none; align-items: center; gap: 6px; margin-inline-start: auto; }
.m-icon-btn {
  position: relative;
  width: 40px; height: 40px; border-radius: 50%;
  display: grid; place-items: center;
  background: var(--surface); color: var(--text);
  font-size: 20px;
  transition: transform 0.12s var(--ease), background 0.15s var(--ease);
}
.m-icon-btn:active { transform: scale(0.92); }
.radio-btn.live { background: var(--primary-soft); color: var(--primary); }
.radio-btn .dot {
  position: absolute; top: 8px; inset-inline-end: 8px;
  width: 7px; height: 7px; border-radius: 50%;
  background: #dc2626; box-shadow: 0 0 0 2px var(--bg);
}

@media (max-width: 900px) {
  .topbar { padding: 10px 16px; gap: 10px; min-height: 60px; }
  .m-brand { display: flex; }
  .m-actions { display: flex; }
  .search { display: none; }
  .topbar.search-open .m-brand,
  .topbar.search-open .m-actions { display: none; }
  .topbar.search-open .search { display: flex; max-width: none; }
  .topbar.search-open .m-close { display: grid; }
}
</style>
