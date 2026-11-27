<script setup>
import VideoThumb from './VideoThumb.vue'
import { relDate, compactNum } from '@/utils/format'
import { useVideoStore } from '@/stores/videos'

const props = defineProps({ video: { type: Object, required: true } })
const vs = useVideoStore()

function onSave(e) {
  e.preventDefault()
  e.stopPropagation()
  vs.toggleSave(props.video)
}
</script>

<template>
  <RouterLink :to="`/videos/${video.id}`" class="vcard">
    <div class="thumb-box">
      <VideoThumb :video="video" />
      <button
        type="button"
        class="bm-btn"
        :class="{ on: vs.isSaved(video.id) }"
        :title="vs.isSaved(video.id) ? 'إزالة من شاهد لاحقًا' : 'حفظ للمشاهدة لاحقًا'"
        :aria-label="vs.isSaved(video.id) ? 'إزالة من شاهد لاحقًا' : 'حفظ للمشاهدة لاحقًا'"
        @click="onSave"
      >
        <i class="ti" :class="vs.isSaved(video.id) ? 'ti-bookmark-filled' : 'ti-bookmark'" aria-hidden="true"></i>
      </button>
      <span v-if="vs.isWatched(video.id)" class="watched-pill">
        <i class="ti ti-check" aria-hidden="true"></i> شُوهد
      </span>
    </div>
    <div class="body">
      <h3>{{ video.title }}</h3>
      <p class="meta">
        <span v-if="video.series" class="series-tag">{{ video.series.title }}</span>
        <template v-else-if="video.topic">{{ video.topic.name }}</template>
        · {{ compactNum(video.views) }} مشاهدة · {{ relDate(video.date) }}
      </p>
    </div>
  </RouterLink>
</template>

<style scoped>
.vcard { display: flex; flex-direction: column; gap: 10px; }
.thumb-box { position: relative; }
.bm-btn {
  position: absolute;
  top: 8px;
  inset-inline-end: 8px;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: rgba(15, 23, 20, 0.72);
  backdrop-filter: blur(6px);
  color: #fff;
  display: grid;
  place-items: center;
  font-size: 17px;
  z-index: 2;
  transition: transform 0.15s var(--ease), background 0.15s var(--ease);
}
.bm-btn:hover { transform: scale(1.08); background: rgba(15, 23, 20, 0.9); }
.bm-btn.on { background: var(--primary); color: var(--on-primary); }
.watched-pill {
  position: absolute;
  top: 8px;
  inset-inline-start: 8px;
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(15, 23, 20, 0.74);
  color: #bbf7d0;
  font-size: 11px;
  font-weight: 500;
  z-index: 2;
}
.vcard:hover :deep(.play) { transform: scale(1.08); }
.vcard:hover h3 { color: var(--primary); }
.body { display: flex; flex-direction: column; gap: 4px; }
h3 {
  font-size: 15px;
  font-weight: 500;
  line-height: 1.5;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  transition: color 0.15s var(--ease);
}
.meta { font-size: 13px; color: var(--text-3); }
.series-tag { color: var(--primary); font-weight: 500; }
</style>
