<script setup>
defineProps({ show: Boolean, loading: Boolean })
defineEmits(['more'])
</script>

<template>
  <div v-if="show" class="more">
    <button class="btn btn-soft" :disabled="loading" @click="$emit('more')">
      <i class="ti" :class="loading ? 'ti-loader-2 spin' : 'ti-chevron-down'" aria-hidden="true"></i>
      {{ loading ? 'جارٍ التحميل…' : 'عرض المزيد' }}
    </button>
  </div>
</template>

<style scoped>
.more { display: flex; justify-content: center; }
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
