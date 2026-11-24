<script setup>
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSiteStore } from '@/stores/site'
import BrandMark from './BrandMark.vue'

const route = useRoute()
const router = useRouter()
const store = useSiteStore()
const q = ref(route.query.q || '')

watch(() => route.query.q, (v) => (q.value = v || ''))

function submit() {
  const term = q.value.trim()
  if (term) router.push({ name: 'search', query: { q: term } })
}
</script>

<template>
  <header class="topbar">
    <RouterLink to="/" class="m-brand">
      <BrandMark />
      <span>{{ store.site.name }}</span>
    </RouterLink>
    <form class="search" role="search" @submit.prevent="submit">
      <i class="ti ti-search" aria-hidden="true"></i>
      <label for="q" class="sr-only">ابحث في المحتوى</label>
      <input id="q" v-model="q" type="search" placeholder="ابحث عن مقطع، درس، أو موضوع…" autocomplete="off" />
    </form>
  </header>
</template>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 10;
  background: color-mix(in srgb, var(--bg) 88%, transparent);
  backdrop-filter: saturate(1.4) blur(10px);
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
  font-weight: 600;
  font-size: 19px;
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

@media (max-width: 900px) {
  .topbar { padding: 10px 16px; gap: 12px; }
  .m-brand { display: flex; }
}
</style>
