<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePlayerStore } from '@/stores/player'
import { usePageTitle } from '@/utils/usePageTitle'
import { date, clock } from '@/utils/format'
import PlayButton from '@/components/PlayButton.vue'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'

const route = useRoute()
const player = usePlayerStore()
const { data, loading, error, reload } = useFetch(() => api.audio(route.params.id))
const track = computed(() => data.value?.item)
const queue = computed(() => data.value?.queue || [])
usePageTitle(() => track.value?.title)
</script>

<template>
  <div class="page">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <template v-if="track">
        <section class="head">
          <div class="art" aria-hidden="true"><i class="ti ti-headphones"></i></div>
          <div class="info">
            <RouterLink v-if="track.series" :to="`/series/${track.series.id}`" class="eyebrow">{{ track.series.title }}</RouterLink>
            <h1>{{ track.title }}</h1>
            <p class="meta">
              <template v-if="track.topic">{{ track.topic.name }} · </template>{{ clock(track.duration) }} · {{ date(track.date) }}
            </p>
            <div class="actions">
              <PlayButton :track="track" :queue="queue" size="lg" />
              <span class="hint">{{ player.isCurrent(track.id) ? 'يُشغَّل الآن' : 'اضغط للاستماع' }}</span>
            </div>
            <p v-if="track.description" class="desc">{{ track.description }}</p>
          </div>
        </section>

        <section v-if="queue.length > 1" class="section">
          <div class="section-head"><h2>{{ track.series ? 'حلقات السلسلة' : 'صوتيات ذات صلة' }}</h2></div>
          <div class="list">
            <FeedItem v-for="a in queue" :key="a.key" :item="a" :queue="queue" :show-badge="false" />
          </div>
        </section>
      </template>
    </StateView>
  </div>
</template>

<style scoped>
.head { display: flex; gap: 24px; align-items: center; background: var(--surface); border-radius: 16px; padding: 20px; }
.art {
  width: 160px;
  aspect-ratio: 1;
  border-radius: 14px;
  background: var(--audio);
  color: var(--audio-text);
  display: grid;
  place-items: center;
  font-size: 56px;
  flex-shrink: 0;
}
.info { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.eyebrow { font-size: 13px; color: var(--primary); font-weight: 500; }
h1 { font-size: 24px; }
.meta { color: var(--text-3); font-size: 14px; }
.actions { display: flex; align-items: center; gap: 12px; margin-top: 6px; }
.hint { color: var(--text-2); font-size: 14px; }
.desc { color: var(--text-2); font-size: 14px; white-space: pre-line; margin-top: 4px; }

@media (max-width: 600px) {
  .head { flex-direction: column; align-items: flex-start; padding: 16px; }
  .art { width: 100%; aspect-ratio: 2.4; font-size: 44px; }
  h1 { font-size: 20px; }
}
</style>
