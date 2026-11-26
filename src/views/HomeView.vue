<script setup>
import { ref, computed } from 'vue'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePlayerStore } from '@/stores/player'
import { useSiteStore } from '@/stores/site'
import { relDate } from '@/utils/format'
import ContinueCard from '@/components/ContinueCard.vue'
import VideoThumb from '@/components/VideoThumb.vue'
import FeedItem from '@/components/FeedItem.vue'
import TopicChips from '@/components/TopicChips.vue'
import SeriesCard from '@/components/SeriesCard.vue'
import StateView from '@/components/StateView.vue'

const player = usePlayerStore()
const store = useSiteStore()
const topic = ref('')

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
