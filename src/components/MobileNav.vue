<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useSiteStore } from '@/stores/site'
import { usePlayerStore } from '@/stores/player'
import { RADIO_STATIONS } from '@/data/muslimTools'

const route = useRoute()
const store = useSiteStore()
const player = usePlayerStore()
const sheetOpen = ref(false)

const topics = computed(() => store.topics)
const site = computed(() => store.site)

const mobileTabs = [
  { to: '/', label: 'الرئيسية', icon: 'ti-home', exact: true },
  { to: '/quran', label: 'المصحف', icon: 'ti-book-2' },
  { to: '/audio', label: 'الصوتيات', icon: 'ti-headphones' },
  { to: '/videos', label: 'المرئيات', icon: 'ti-player-play' }
]

const moreActive = computed(() =>
  route.path.startsWith('/tools') ||
  route.path.startsWith('/lessons') ||
  route.path.startsWith('/series') ||
  route.path.startsWith('/posts') ||
  route.path.startsWith('/topics')
)

watch(() => route.fullPath, () => {
  sheetOpen.value = false
})

function playRadio() {
  player.play(RADIO_STATIONS[0])
  sheetOpen.value = false
}
</script>

<template>
  <!-- Bottom Sheet ("المزيد" — الدروس، المنشورات، الإذاعة، وكل المواضيع) -->
  <Transition name="sheet-fade">
    <div v-if="sheetOpen" class="sheet-backdrop" @click="sheetOpen = false"></div>
  </Transition>
  <Transition name="sheet-up">
    <aside v-if="sheetOpen" class="sheet" aria-label="قائمة المزيد">
      <div class="sheet-handle" @click="sheetOpen = false"><span></span></div>

      <div class="sheet-head">
        <strong>استكشف {{ site.name }}</strong>
        <button type="button" class="sheet-close" aria-label="إغلاق" @click="sheetOpen = false">
          <i class="ti ti-x"></i>
        </button>
      </div>

      <div class="sheet-quick">
        <RouterLink to="/tools" class="q-tile">
          <i class="ti ti-compass"></i>
          <span>أدوات المسلم</span>
        </RouterLink>
        <button type="button" class="q-tile radio" @click="playRadio">
          <i class="ti ti-radio"></i>
          <span>إذاعة القرآن مباشر</span>
        </button>
        <RouterLink to="/lessons" class="q-tile">
          <i class="ti ti-book"></i>
          <span>الدروس والسلاسل</span>
        </RouterLink>
        <RouterLink to="/posts" class="q-tile">
          <i class="ti ti-notes"></i>
          <span>المنشورات</span>
        </RouterLink>
      </div>

      <div v-if="topics.length" class="sheet-section">
        <span class="sheet-lbl">المواضيع</span>
        <div class="topic-grid">
          <RouterLink
            v-for="t in topics"
            :key="t.slug"
            :to="`/topics/${t.slug}`"
            class="topic-pill"
            active-class="active"
          >
            <i class="ti" :class="t.icon" aria-hidden="true"></i>
            <span>{{ t.name }}</span>
          </RouterLink>
        </div>
      </div>

      <div v-if="site.youtubeChannelUrl" class="sheet-foot">
        <a :href="site.youtubeChannelUrl" target="_blank" rel="noopener" class="yt-link">
          <i class="ti ti-brand-youtube"></i>
          <span>القناة الرسمية على يوتيوب</span>
          <i class="ti ti-external-link" style="margin-inline-start:auto"></i>
        </a>
      </div>
    </aside>
  </Transition>

  <!-- Modern 5-Tab Frosted Bottom Bar -->
  <nav class="mnav" aria-label="التنقل السفلي">
    <RouterLink
      v-for="item in mobileTabs"
      :key="item.to"
      :to="item.to"
      class="mitem"
      :exact-active-class="item.exact ? 'active' : ''"
      :active-class="item.exact ? '' : 'active'"
    >
      <span class="micon"><i class="ti" :class="item.icon" aria-hidden="true"></i></span>
      <span>{{ item.label }}</span>
    </RouterLink>

    <button
      type="button"
      class="mitem"
      :class="{ active: sheetOpen || moreActive }"
      aria-label="المزيد"
      @click="sheetOpen = !sheetOpen"
    >
      <span class="micon"><i class="ti" :class="sheetOpen ? 'ti-x' : 'ti-layout-grid'" aria-hidden="true"></i></span>
      <span>المزيد</span>
    </button>
  </nav>
</template>

<style scoped>
.mnav, .sheet, .sheet-backdrop { display: none; }

@media (max-width: 900px) {
  .mnav {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    height: calc(var(--mobile-nav-h) + env(safe-area-inset-bottom));
    padding: 6px 8px calc(6px + env(safe-area-inset-bottom));
    background: color-mix(in srgb, var(--bg) 90%, transparent);
    backdrop-filter: saturate(1.6) blur(16px);
    -webkit-backdrop-filter: saturate(1.6) blur(16px);
    border-top: 1px solid var(--border);
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    align-items: center;
    z-index: 35;
  }
  .mitem {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 11px;
    font-weight: 500;
    color: var(--text-3);
    transition: color 0.15s var(--ease);
  }
  .micon {
    width: 44px;
    height: 28px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    transition: background 0.18s var(--ease), transform 0.12s var(--ease);
  }
  .mitem:active .micon { transform: scale(0.9); }
  .mitem .ti { font-size: 21px; }
  .mitem.active { color: var(--primary); font-weight: 700; }
  .mitem.active .micon { background: var(--primary-soft); }

  .sheet-backdrop {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.42);
    backdrop-filter: blur(3px);
    z-index: 32;
  }
  .sheet {
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: fixed;
    inset-inline: 0;
    bottom: calc(var(--mobile-nav-h) + env(safe-area-inset-bottom));
    max-height: 76vh;
    overflow-y: auto;
    background: var(--bg);
    border-top: 1px solid var(--border);
    border-radius: 22px 22px 0 0;
    padding: 10px 18px 22px;
    box-shadow: 0 -14px 40px rgba(0, 0, 0, 0.16);
    z-index: 34;
  }
  .sheet-handle { display: flex; justify-content: center; padding: 4px 0 2px; cursor: pointer; }
  .sheet-handle span { width: 40px; height: 4px; border-radius: 999px; background: var(--border-strong); }
  .sheet-head { display: flex; align-items: center; justify-content: space-between; }
  .sheet-head strong { font-size: 17px; }
  .sheet-close {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--surface); color: var(--text-2);
    display: grid; place-items: center; font-size: 18px;
  }
  .sheet-quick { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .q-tile {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 14px; border-radius: 14px;
    background: var(--surface); border: 1px solid var(--border);
    font-size: 14px; font-weight: 500; color: var(--text); text-align: right;
  }
  .q-tile:active { transform: scale(0.97); }
  .q-tile .ti { font-size: 20px; color: var(--primary); }
  .q-tile.radio { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); }

  .sheet-section { display: flex; flex-direction: column; gap: 10px; }
  .sheet-lbl { font-size: 12.5px; font-weight: 600; color: var(--text-3); }
  .topic-grid { display: flex; flex-wrap: wrap; gap: 8px; }
  .topic-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 13px; border-radius: 999px;
    background: var(--surface); border: 1px solid var(--border);
    font-size: 13.5px; color: var(--text-2);
  }
  .topic-pill.active { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

  .sheet-foot { border-top: 1px solid var(--border); padding-top: 12px; }
  .yt-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 12px;
    background: var(--surface); color: var(--text-2); font-size: 14px;
  }
  .yt-link .ti-brand-youtube { font-size: 20px; color: #dc2626; }

  .sheet-fade-enter-active, .sheet-fade-leave-active { transition: opacity 0.2s ease; }
  .sheet-fade-enter-from, .sheet-fade-leave-to { opacity: 0; }
  .sheet-up-enter-active, .sheet-up-leave-active { transition: transform 0.26s var(--ease); }
  .sheet-up-enter-from, .sheet-up-leave-to { transform: translateY(100%); }
}
</style>
