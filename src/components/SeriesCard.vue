<script setup>
import { seriesKind as kind } from '@/utils/types'
import { toArDigits } from '@/utils/format'

defineProps({ series: { type: Object, required: true } })
</script>

<template>
  <RouterLink :to="`/series/${series.id}`" class="scard">
    <div class="cover" :class="kind[series.kind]?.cls" aria-hidden="true">
      <i class="ti ti-stack-2"></i>
    </div>
    <div class="body">
      <h3>{{ series.title }}</h3>
      <p v-if="series.description" class="desc">{{ series.description }}</p>
      <p class="meta">
        <i class="ti" :class="kind[series.kind]?.icon" aria-hidden="true"></i>
        {{ toArDigits(series.count) }} {{ series.kind === 'audio' ? 'حلقات' : 'دروس' }} · {{ kind[series.kind]?.label }}
        <template v-if="series.topic"> · {{ series.topic.name }}</template>
      </p>
    </div>
  </RouterLink>
</template>

<style scoped>
.scard {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  background: var(--bg);
  transition: border-color 0.15s var(--ease), transform 0.2s var(--ease);
}
.scard:hover { border-color: var(--border-strong); transform: translateY(-2px); }
.cover {
  height: 96px;
  display: grid;
  place-items: center;
  font-size: 32px;
}
.cover.video { background: var(--video); color: var(--video-text); }
.cover.audio { background: var(--audio); color: var(--audio-text); }
.cover.lesson { background: var(--lesson); color: var(--lesson-text); }
.body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; }
h3 { font-size: 16px; font-weight: 500; }
.desc {
  font-size: 13px;
  color: var(--text-2);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.meta { font-size: 12px; color: var(--text-3); display: flex; align-items: center; gap: 4px; margin-top: 4px; }
</style>
