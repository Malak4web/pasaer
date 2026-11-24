<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePlayerStore } from '@/stores/player'
import { usePageTitle } from '@/utils/usePageTitle'
import { toArDigits } from '@/utils/format'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'

const route = useRoute()
const player = usePlayerStore()
const { data, loading, error, reload } = useFetch(() => api.series(route.params.id))
const s = computed(() => data.value?.series)
const items = computed(() => data.value?.items || [])
const audioQueue = computed(() => items.value.filter((x) => x.type === 'audio'))
const kindLabel = { video: 'سلسلة مرئية', audio: 'سلسلة صوتية', text: 'سلسلة مكتوبة' }
usePageTitle(() => s.value?.title)
</script>

<template>
  <div class="page">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <template v-if="s">
        <header class="shead">
          <span class="eyebrow">{{ kindLabel[s.kind] }}<template v-if="s.topic"> · {{ s.topic.name }}</template></span>
          <h1>{{ s.title }}</h1>
          <p v-if="s.description">{{ s.description }}</p>
          <div class="actions">
            <span class="count">{{ toArDigits(items.length) }} {{ s.kind === 'audio' ? 'حلقات' : 'دروس' }}</span>
            <button v-if="audioQueue.length" class="btn btn-primary" @click="player.play(audioQueue[0], audioQueue)">
              <i class="ti ti-player-play" aria-hidden="true"></i> ابدأ الاستماع
            </button>
          </div>
        </header>
        <p v-if="!items.length" class="empty">لسه ما فيش دروس منشورة في السلسلة دي.</p>
        <ol v-else class="steps">
          <li v-for="(it, i) in items" :key="it.key">
            <span class="n" aria-hidden="true">{{ toArDigits(i + 1) }}</span>
            <FeedItem :item="it" :queue="audioQueue" :show-badge="false" />
          </li>
        </ol>
      </template>
    </StateView>
  </div>
</template>

<style scoped>
.shead { background: var(--surface); border-radius: 16px; padding: 24px; display: flex; flex-direction: column; gap: 8px; }
.eyebrow { font-size: 13px; color: var(--primary); font-weight: 500; }
.shead h1 { font-size: 26px; }
.shead p { color: var(--text-2); max-width: 60ch; }
.actions { display: flex; align-items: center; gap: 14px; margin-top: 6px; }
.count { font-size: 14px; color: var(--text-3); }
.steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.steps li { display: flex; align-items: center; gap: 12px; }
.steps li > :last-child { flex: 1; min-width: 0; }
.n {
  width: 30px; height: 30px; border-radius: 50%;
  display: grid; place-items: center;
  font-size: 13px; font-weight: 500;
  background: var(--primary-soft); color: var(--primary);
  flex-shrink: 0;
}
@media (max-width: 560px) {
  .shead { padding: 16px; }
  .shead h1 { font-size: 21px; }
  .n { display: none; }
}
</style>
