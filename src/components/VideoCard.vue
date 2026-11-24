<script setup>
import VideoThumb from './VideoThumb.vue'
import { relDate, compactNum } from '@/utils/format'
defineProps({ video: { type: Object, required: true } })
</script>

<template>
  <RouterLink :to="`/videos/${video.id}`" class="vcard">
    <VideoThumb :video="video" />
    <div class="body">
      <h3>{{ video.title }}</h3>
      <p class="meta">
        <template v-if="video.topic">{{ video.topic.name }} · </template>{{ compactNum(video.views) }} مشاهدة · {{ relDate(video.date) }}
      </p>
    </div>
  </RouterLink>
</template>

<style scoped>
.vcard { display: flex; flex-direction: column; gap: 10px; }
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
</style>
