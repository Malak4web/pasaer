<script setup>
import { useSiteStore } from '@/stores/site'
defineProps({ modelValue: { type: String, default: '' } })
defineEmits(['update:modelValue'])
const store = useSiteStore()
</script>

<template>
  <div class="chips" role="group" aria-label="تصفية حسب الموضوع">
    <button class="chip" :class="{ on: !modelValue }" :aria-pressed="!modelValue" @click="$emit('update:modelValue', '')">الكل</button>
    <button
      v-for="t in store.topics"
      :key="t.slug"
      class="chip"
      :class="{ on: modelValue === t.slug }"
      :aria-pressed="modelValue === t.slug"
      @click="$emit('update:modelValue', t.slug)"
    >
      {{ t.name }}
    </button>
  </div>
</template>

<style scoped>
.chips {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 2px;
}
.chips::-webkit-scrollbar { display: none; }
.chip {
  flex-shrink: 0;
  height: 34px;
  padding: 0 14px;
  border-radius: 999px;
  font-size: 14px;
  background: var(--primary-soft);
  color: var(--primary);
  transition: background 0.15s var(--ease), color 0.15s var(--ease);
}
.chip:hover { background: var(--surface-2); }
.chip.on { background: var(--primary); color: var(--on-primary); }
</style>
