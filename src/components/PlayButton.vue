<script setup>
import { computed } from 'vue'
import { usePlayerStore } from '@/stores/player'

const props = defineProps({
  track: { type: Object, required: true },
  queue: { type: Array, default: null },
  size: { type: String, default: 'md' }
})
const player = usePlayerStore()
const active = computed(() => player.isCurrent(props.track.id) && player.playing)

function onClick() {
  if (player.isCurrent(props.track.id)) player.toggle()
  else player.play(props.track, props.queue)
}
</script>

<template>
  <button
    class="pbtn"
    :class="[size, { active }]"
    :aria-label="active ? `إيقاف ${track.title}` : `تشغيل ${track.title}`"
    @click.stop.prevent="onClick"
  >
    <i class="ti" :class="active ? 'ti-player-pause' : 'ti-player-play'" aria-hidden="true"></i>
  </button>
</template>

<style scoped>
.pbtn {
  flex-shrink: 0;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: var(--audio);
  color: var(--audio-text);
  font-size: 20px;
  transition: background 0.15s var(--ease), color 0.15s var(--ease), transform 0.15s var(--ease);
}
.pbtn:hover { transform: scale(1.05); }
.pbtn.active { background: var(--primary); color: var(--on-primary); }
.pbtn.lg { width: 56px; height: 56px; font-size: 26px; background: var(--primary); color: var(--on-primary); }
.pbtn.lg:hover { background: var(--primary-hover); }
</style>
