<script setup>
import { typeMeta } from '@/utils/types'
defineProps({ type: { type: String, required: true } })
</script>

<template>
  <span class="badge" :class="type">
    <i class="ti" :class="typeMeta[type].icon" aria-hidden="true"></i>
    {{ typeMeta[type].label }}
  </span>
</template>

<style scoped>
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  font-weight: 500;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 999px;
  width: fit-content;
}
.badge .ti { font-size: 13px; }
.video { background: var(--video); color: var(--video-text); }
.audio { background: var(--audio); color: var(--audio-text); }
.lesson { background: var(--lesson); color: var(--lesson-text); }
.post { background: var(--post); color: var(--post-text); }
</style>
