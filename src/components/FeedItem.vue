<script setup>
import { computed } from 'vue'
import TypeBadge from './TypeBadge.vue'
import VideoThumb from './VideoThumb.vue'
import PlayButton from './PlayButton.vue'
import { typeMeta, itemLink } from '@/utils/types'
import { relDate, clock, toArDigits } from '@/utils/format'
import { usePlayerStore } from '@/stores/player'

const props = defineProps({
  item: { type: Object, required: true },
  queue: { type: Array, default: null },
  showBadge: { type: Boolean, default: true }
})
const player = usePlayerStore()

const link = computed(() => itemLink(props.item))
const isNow = computed(() => props.item.type === 'audio' && player.isCurrent(props.item.id))
const prog = computed(() => (props.item.type === 'audio' ? player.progressOf(props.item.id) : 0))

const meta = computed(() => {
  const x = props.item
  const parts = []
  if (x.series) parts.push(x.series.title)
  else if (x.topic) parts.push(x.topic.name)
  if (x.type === 'audio') parts.push(clock(x.duration))
  if (x.readMinutes) parts.push(`${toArDigits(x.readMinutes)} دقائق قراءة`)
  parts.push(relDate(x.date))
  return parts.join(' · ')
})
</script>

<template>
  <RouterLink :to="link" class="feed" :class="{ now: isNow }">
    <div class="lead">
      <VideoThumb v-if="item.type === 'video'" :video="item" size="sm" />
      <PlayButton v-else-if="item.type === 'audio'" :track="item" :queue="queue" />
      <div v-else class="ico" :class="item.type" aria-hidden="true">
        <i class="ti" :class="typeMeta[item.type].icon"></i>
      </div>
    </div>
    <div class="body">
      <TypeBadge v-if="showBadge" :type="item.type" />
      <h3>{{ item.title }}</h3>
      <p v-if="item.excerpt" class="excerpt">{{ item.excerpt }}</p>
      <p class="meta">{{ meta }}</p>
      <div v-if="prog > 0.01" class="bar" role="progressbar" :aria-valuenow="Math.round(prog * 100)" aria-valuemin="0" aria-valuemax="100" aria-label="نسبة الاستماع">
        <span :style="{ width: `${prog * 100}%` }"></span>
      </div>
    </div>
  </RouterLink>
</template>

<style scoped>
.feed {
  display: flex;
  gap: 14px;
  align-items: center;
  padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  background: var(--bg);
  transition: border-color 0.15s var(--ease), background 0.15s var(--ease);
}
.feed:hover { border-color: var(--border-strong); background: var(--surface); }
.feed.now { border-color: var(--primary); }
.lead { flex-shrink: 0; width: 120px; display: flex; justify-content: center; }
.lead :deep(.thumb) { width: 120px; }
.ico {
  width: 120px;
  aspect-ratio: 16 / 9;
  border-radius: 10px;
  display: grid;
  place-items: center;
  font-size: 26px;
}
.ico.lesson { background: var(--lesson); color: var(--lesson-text); }
.ico.post { background: var(--post); color: var(--post-text); }
.body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
h3 { font-size: 15px; font-weight: 500; line-height: 1.5; }
.feed:hover h3 { color: var(--primary); }
.excerpt {
  font-size: 14px;
  color: var(--text-2);
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.meta { font-size: 13px; color: var(--text-3); }
.bar { height: 3px; background: var(--surface-2); border-radius: 2px; overflow: hidden; margin-top: 2px; max-width: 240px; }
.bar span { display: block; height: 100%; background: var(--primary); }

@media (max-width: 560px) {
  .lead, .lead :deep(.thumb), .ico { width: 88px; }
  .ico { font-size: 22px; }
  .excerpt { display: none; }
  .feed { gap: 12px; padding: 10px; }
}
</style>
