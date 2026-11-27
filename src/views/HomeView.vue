<script setup>
import { ref, computed } from 'vue'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePlayerStore } from '@/stores/player'
import { useSiteStore } from '@/stores/site'
import { useQuranStore } from '@/stores/quran'
import { RADIO_STATIONS } from '@/data/muslimTools'
import { relDate, toArDigits } from '@/utils/format'
import ContinueCard from '@/components/ContinueCard.vue'
import VideoThumb from '@/components/VideoThumb.vue'
import FeedItem from '@/components/FeedItem.vue'
import TopicChips from '@/components/TopicChips.vue'
import SeriesCard from '@/components/SeriesCard.vue'
import StateView from '@/components/StateView.vue'

const player = usePlayerStore()
const store = useSiteStore()
const quran = useQuranStore()
const topic = ref('')

function toggleRadio() {
  if (player.isLive && player.playing) player.pause()
  else player.play(RADIO_STATIONS[0])
}

const home = useFetch(() => api.home())
const feed = useFetch(() => api.feed({ topic: topic.value, per_page: 8 }), [topic])

const featured = computed(() => home.data.value?.featured)
const items = computed(() => (feed.data.value?.data || []).filter((x) => x.key !== featured.value?.key))
const audioQueue = computed(() => items.value.filter((x) => x.type === 'audio'))
const resume = computed(() => player.continueListening[0])

async function playLatestAudio() {
  const a = home.data.value?.latestAudio
  if (!a) return
  const res = await api.audio(a.id).catch(() => null)
  player.play(res?.item || a, res?.queue)
}
</script>

<template>
  <div class="page">
    <ContinueCard v-if="resume" :entry="resume" />

    <!-- بوابة الوصول السريع الهادئة -->
    <section class="hub-grid" aria-label="الوصول السريع">
      <RouterLink to="/quran" class="hub-card">
        <span class="hub-ico"><i class="ti ti-book-2"></i></span>
        <span class="hub-txt">
          <strong>المصحف الشريف</strong>
          <small>{{ quran.lastRead ? `متابعة صفحة ${toArDigits(quran.lastRead.page || 1)}` : '٦٠٤ صفحة · ٨ روايات' }}</small>
        </span>
      </RouterLink>

      <button type="button" class="hub-card" :class="{ live: player.isLive && player.playing }" @click="toggleRadio">
        <span class="hub-ico"><i class="ti" :class="player.isLive && player.playing ? 'ti-player-pause' : 'ti-radio'"></i></span>
        <span class="hub-txt">
          <strong>إذاعة القرآن</strong>
          <small>{{ player.isLive && player.playing ? 'بث مباشر شغّال الآن' : 'القاهرة · بث مباشر' }}</small>
        </span>
      </button>

      <RouterLink to="/tools?tab=prayer" class="hub-card">
        <span class="hub-ico"><i class="ti ti-clock"></i></span>
        <span class="hub-txt">
          <strong>أدوات المسلم</strong>
          <small>الصلاة · الأذكار · الزكاة</small>
        </span>
      </RouterLink>

      <RouterLink to="/audio" class="hub-card">
        <span class="hub-ico"><i class="ti ti-headphones"></i></span>
        <span class="hub-txt">
          <strong>المكتبة الصوتية</strong>
          <small>خطب ودروس وتلاوات</small>
        </span>
      </RouterLink>
    </section>

    <StateView :loading="home.loading.value" :error="home.error.value" skeleton="page" @retry="home.reload">
      <section v-if="featured" class="hero" aria-labelledby="hero-title">
        <RouterLink :to="`/videos/${featured.id}`" class="hero-media">
          <VideoThumb :video="featured" />
        </RouterLink>
        <div class="hero-body">
          <span class="eyebrow"><i class="ti ti-sparkles" aria-hidden="true"></i> أحدث مقطع · {{ relDate(featured.date) }}</span>
          <h1 id="hero-title">{{ featured.title }}</h1>
          <p class="lead">{{ store.site.tagline }}.</p>
          <div class="actions">
            <RouterLink :to="`/videos/${featured.id}`" class="btn btn-primary">
              <i class="ti ti-player-play" aria-hidden="true"></i> شاهد الآن
            </RouterLink>
            <button v-if="home.data.value?.latestAudio" class="btn btn-soft" @click="playLatestAudio">
              <i class="ti ti-headphones" aria-hidden="true"></i> استمع لأحدث صوتية
            </button>
          </div>
        </div>
      </section>
    </StateView>

    <section class="section" aria-labelledby="feed-title">
      <div class="section-head">
        <h2 id="feed-title">آخر الإضافات</h2>
      </div>
      <TopicChips v-model="topic" />
      <StateView
        :loading="feed.loading.value"
        :error="feed.error.value"
        :empty="!items.length"
        empty-text="لا يوجد محتوى في هذا الموضوع بعد. جرّب موضوعًا آخر."
        @retry="feed.reload"
      >
        <div class="list">
          <FeedItem v-for="item in items" :key="item.key" :item="item" :queue="audioQueue" />
        </div>
      </StateView>
    </section>

    <section v-if="home.data.value?.series?.length" class="section" aria-labelledby="series-title">
      <div class="section-head">
        <h2 id="series-title">سلاسل مختارة</h2>
        <RouterLink to="/lessons">كل السلاسل ←</RouterLink>
      </div>
      <div class="grid-cards series-row">
        <SeriesCard v-for="s in home.data.value.series" :key="s.id" :series="s" />
      </div>
    </section>
  </div>
</template>

<style scoped>
.hub-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.hub-card {
  display: flex; align-items: center; gap: 12px; text-align: right;
  padding: 14px; border-radius: var(--radius);
  background: var(--card); border: 1px solid var(--border); box-shadow: var(--shadow-sm);
  transition: border-color .15s var(--ease), transform .12s var(--ease), background .15s var(--ease);
}
.hub-card:hover { border-color: var(--primary); background: var(--surface); }
.hub-card:active { transform: scale(0.98); }
.hub-card.live { border-color: var(--primary); background: var(--primary-soft); }
.hub-ico {
  width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-size: 21px;
}
.hub-card.live .hub-ico { background: var(--primary); color: var(--on-primary); }
.hub-txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.35; }
.hub-txt strong { font-size: 14.5px; font-weight: 700; color: var(--text); }
.hub-txt small { font-size: 12px; color: var(--text-3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 900px) {
  .hub-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
  .hub-card { padding: 12px; }
}

.hero {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: 28px;
  align-items: center;
  background: var(--surface);
  border-radius: 16px;
  padding: 18px;
}
.hero-media :deep(.thumb) { border-radius: 12px; }
.hero-media :deep(.play) { width: 60px; height: 60px; font-size: 26px; }
.hero-media:hover :deep(.play) { transform: scale(1.08); }
.hero-body { display: flex; flex-direction: column; gap: 10px; padding-inline-end: 8px; }
.eyebrow { font-size: 13px; color: var(--primary); font-weight: 500; display: inline-flex; gap: 4px; align-items: center; }
.hero-body h1 { font-size: 26px; line-height: 1.45; }
.lead { color: var(--text-2); font-size: 15px; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px; }

@media (max-width: 760px) {
  .hero { grid-template-columns: 1fr; gap: 14px; padding: 12px; border-radius: 18px; }
  .hero-body { padding: 0 4px 4px; }
  .hero-body h1 { font-size: 21px; }
  .actions { display: grid; grid-template-columns: 1fr 1fr; }
  .actions .btn { justify-content: center; height: 42px; }
  .series-row {
    display: flex;
    overflow-x: auto;
    gap: 12px;
    margin-inline: -16px;
    padding: 2px 16px 8px;
    scrollbar-width: none;
    scroll-snap-type: x mandatory;
  }
  .series-row::-webkit-scrollbar { display: none; }
  .series-row > * { flex: 0 0 78%; scroll-snap-align: start; }
}
</style>
