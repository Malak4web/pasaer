<script setup>
import { mainNav } from './nav'
import { computed } from 'vue'
import { useSiteStore } from '@/stores/site'
import BrandMark from './BrandMark.vue'

const store = useSiteStore()
const site = computed(() => store.site)
const topics = computed(() => store.topics)
</script>

<template>
  <aside class="sidebar" aria-label="القائمة الرئيسية">
    <RouterLink to="/" class="brand">
      <BrandMark />
      <span>{{ site.name }}</span>
    </RouterLink>

    <nav class="nav">
      <RouterLink
        v-for="item in mainNav"
        :key="item.to"
        :to="item.to"
        class="nav-item"
        :exact-active-class="item.to === '/' ? 'active' : ''"
        :active-class="item.to === '/' ? '' : 'active'"
      >
        <i class="ti" :class="item.icon" aria-hidden="true"></i>
        {{ item.label }}
      </RouterLink>
    </nav>

    <div class="group">
      <span class="group-label">المواضيع</span>
      <RouterLink
        v-for="t in topics"
        :key="t.slug"
        :to="`/topics/${t.slug}`"
        class="nav-item small"
        active-class="active"
      >
        <i class="ti" :class="t.icon" aria-hidden="true"></i>
        {{ t.name }}
      </RouterLink>
    </div>

    <div class="foot">
      <p v-if="site.verse?.text" class="verse">﴿{{ site.verse.text }}﴾</p>
      <a v-if="site.youtubeChannelUrl" :href="site.youtubeChannelUrl" target="_blank" rel="noopener" class="yt">
        <i class="ti ti-brand-youtube" aria-hidden="true"></i>
        القناة على يوتيوب
      </a>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  position: fixed;
  inset-block: 0;
  inset-inline-start: 0;
  width: var(--sidebar-w);
  background: var(--surface);
  border-inline-end: 1px solid var(--border);
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 22px;
  overflow-y: auto;
  z-index: 20;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 8px;
  font-size: 22px;
  font-weight: 600;
  color: var(--primary);
}
.nav, .group { display: flex; flex-direction: column; gap: 2px; }
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  font-size: 15px;
  color: var(--text-2);
  transition: background 0.15s var(--ease), color 0.15s var(--ease);
}
.nav-item .ti { font-size: 20px; }
.nav-item:hover { background: var(--surface-2); color: var(--text); }
.nav-item.active { background: var(--primary); color: var(--on-primary); }
.nav-item.small { font-size: 14px; padding: 7px 12px; }
.nav-item.small .ti { font-size: 18px; }
.group-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-3);
  padding: 0 12px 6px;
}
.foot {
  margin-top: auto;
  padding: 14px 12px 0;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.verse {
  font-family: var(--font-quote);
  font-size: 17px;
  color: var(--text-2);
  line-height: 1.8;
}
.yt {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text-2);
}
.yt:hover { color: var(--primary); }
.yt .ti { font-size: 18px; }

@media (max-width: 900px) {
  .sidebar { display: none; }
}
</style>
