<script setup>
import { computed, ref } from 'vue'
import { usePlayerStore } from '@/stores/player'
import { api } from '@/api'
import { clock } from '@/utils/format'

const props = defineProps({ entry: { type: Object, required: true } })
const player = usePlayerStore()
const t = computed(() => props.entry.track)
const pct = computed(() => Math.min(100, (props.entry.position / (props.entry.duration || 1)) * 100))
const remaining = computed(() => clock(props.entry.duration - props.entry.position))
const label = computed(() => t.value.series?.title || 'صوتية')
const active = computed(() => player.isCurrent(t.value.id) && player.playing)
const busy = ref(false)

async function go() {
  if (player.isCurrent(t.value.id)) return player.toggle()
  busy.value = true
  try {
    // نجيب أحدث بيانات الصوتية + قائمة التشغيل (حلقات السلسلة)
    const res = await api.audio(t.value.id)
    player.play(res.item, res.queue)
  } catch {
    player.play(t.value)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="cont">
    <div class="info">
      <span class="eyebrow">أكمل من حيث توقفت · {{ label }}</span>
      <h3>{{ t.title }}</h3>
      <div class="bar"><span :style="{ width: pct + '%' }"></span></div>
      <span class="rem">متبقٍّ {{ remaining }}</span>
    </div>
    <button class="go" :disabled="busy" @click="go">
      <i class="ti" :class="active ? 'ti-player-pause' : 'ti-player-play'" aria-hidden="true"></i>
      {{ active ? 'إيقاف' : 'تابع' }}
    </button>
  </div>
</template>

<style scoped>
.cont {
  background: var(--primary);
  color: var(--on-primary);
  border-radius: var(--radius);
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
}
.info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.eyebrow { font-size: 12px; opacity: 0.8; }
h3 { font-size: 17px; font-weight: 500; }
.bar { height: 4px; background: color-mix(in srgb, var(--on-primary) 25%, transparent); border-radius: 2px; overflow: hidden; max-width: 360px; }
.bar span { display: block; height: 100%; background: var(--sand); border-radius: 2px; }
.rem { font-size: 12px; opacity: 0.8; }
.go {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 40px;
  padding: 0 18px;
  border-radius: 999px;
  background: var(--on-primary);
  color: var(--primary);
  font-weight: 500;
  font-size: 14px;
}
.go:hover { opacity: 0.92; }
.go:disabled { opacity: 0.6; }
</style>
