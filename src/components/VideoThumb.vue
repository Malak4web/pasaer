<script setup>
import { clock } from '@/utils/format'
defineProps({
  video: { type: Object, required: true },
  size: { type: String, default: 'md' }
})
</script>

<template>
  <div class="thumb" :class="size">
    <img
      v-if="video.youtubeId"
      :src="`https://i.ytimg.com/vi/${video.youtubeId}/hqdefault.jpg`"
      :alt="''"
      loading="lazy"
    />
    <div v-else class="ph" aria-hidden="true">
      <svg viewBox="0 0 160 90" preserveAspectRatio="xMidYMid slice">
        <g fill="none" stroke="currentColor" stroke-width="0.6" opacity="0.35">
          <path d="M80 5 L95 20 L115 20 L115 40 L130 45 L115 50 L115 70 L95 70 L80 85 L65 70 L45 70 L45 50 L30 45 L45 40 L45 20 L65 20 Z" />
          <circle cx="80" cy="45" r="18" />
        </g>
      </svg>
    </div>
    <span class="play" aria-hidden="true"><i class="ti ti-player-play"></i></span>
    <span v-if="video.duration" class="dur">{{ clock(video.duration) }}</span>
  </div>
</template>

<style scoped>
.thumb {
  position: relative;
  aspect-ratio: 16 / 9;
  border-radius: 10px;
  overflow: hidden;
  background: var(--video);
  color: var(--video-text);
}
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.ph, .ph svg { width: 100%; height: 100%; }
.play {
  position: absolute;
  inset: 0;
  margin: auto;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: color-mix(in srgb, var(--bg) 90%, transparent);
  color: var(--primary);
  display: grid;
  place-items: center;
  font-size: 20px;
  transition: transform 0.2s var(--ease);
}
.sm .play { width: 30px; height: 30px; font-size: 15px; }
.dur {
  position: absolute;
  bottom: 6px;
  inset-inline-end: 6px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  font-size: 11px;
  padding: 1px 6px;
  border-radius: 4px;
}
</style>
