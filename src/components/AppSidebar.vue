<script setup>
import { computed } from 'vue'
import { useSiteStore } from '@/stores/site'
import BrandMark from './BrandMark.vue'

const store = useSiteStore()
const site = computed(() => store.site)
const topics = computed(() => store.topics)

const contentNav = [
  { to: '/', label: 'الرئيسية', icon: 'ti-home', exact: true },
  { to: '/videos', label: 'المرئيات', icon: 'ti-player-play' },
  { to: '/audio', label: 'الصوتيات', icon: 'ti-headphones' },
  { to: '/lessons', label: 'الدروس والسلاسل', icon: 'ti-book' },
  { to: '/posts', label: 'المنشورات', icon: 'ti-notes' }
]

const dailyNav = [
  { to: '/quran', label: 'المصحف الشريف', icon: 'ti-book-2' },
  { to: '/tools', label: 'أدوات المسلم والإذاعة', icon: 'ti-compass' }
]
</script>

<template>
  <aside class="sidebar" aria-label="القائمة الرئيسية">
    <RouterLink to="/" class="brand">
      <BrandMark />
      <span>{{ site.name }}</span>
    </RouterLink>

    <div class="group">
      <span class="group-label">المكتبة</span>
      <nav class="nav">
        <RouterLink
          v-for="item in contentNav"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :exact-active-class="item.exact ? 'active' : ''"
          :active-class="item.exact ? '' : 'active'"
        >
          <i class="ti" :class="item.icon" aria-hidden="true"></i>
          {{ item.label }}
        </RouterLink>
      </nav>
    </div>

    <div class="group">
      <span class="group-label">زاد المسلم اليومي</span>
      <nav class="nav">
        <RouterLink
          v-for="item in dailyNav"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          active-class="active"
        >
          <i class="ti" :class="item.icon" aria-hidden="true"></i>
          {{ item.label }}
        </RouterLink>
      </nav>
    </div>

    <div v-if="topics.length" class="group">
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
  background: var(--card);
  border-inline-end: 1px solid var(--border);
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow-y: auto;
  z-index: 20;
}
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 8px;
  font-size: 22px;
  font-weight: 700;
  color: var(--primary);
}
.nav, .group { display: flex; flex-direction: column; gap: 2px; }
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 11px;
  font-size: 14.5px;
  font-weight: 500;
  color: var(--text-2);
  transition: background 0.15s var(--ease), color 0.15s var(--ease);
}
.nav-item .ti { font-size: 19px; }
.nav-item:hover { background: var(--surface); color: var(--text); }
.nav-item.active { background: var(--primary); color: var(--on-primary); }
.nav-item.small { font-size: 13.5px; font-weight: 400; padding: 7px 12px; }
.nav-item.small .ti { font-size: 17px; }
.group-label {
  font-size: 11.5px;
  font-weight: 600;
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
