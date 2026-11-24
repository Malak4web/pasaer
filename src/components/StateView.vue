<script setup>
// حالة التحميل / الخطأ / الفراغ لأي قائمة أو صفحة
defineProps({
  loading: Boolean,
  error: { type: Object, default: null },
  empty: Boolean,
  emptyText: { type: String, default: 'لا يوجد محتوى هنا بعد.' },
  skeleton: { type: String, default: 'list' } // list | grid | page
})
defineEmits(['retry'])
</script>

<template>
  <div v-if="loading" class="skel" :class="skeleton" aria-busy="true" aria-label="جارٍ التحميل">
    <template v-if="skeleton === 'grid'">
      <div v-for="i in 6" :key="i" class="sk-card"><div class="sk sk-thumb"></div><div class="sk sk-line"></div><div class="sk sk-line short"></div></div>
    </template>
    <template v-else-if="skeleton === 'page'">
      <div class="sk sk-hero"></div><div class="sk sk-line"></div><div class="sk sk-line"></div><div class="sk sk-line short"></div>
    </template>
    <template v-else>
      <div v-for="i in 5" :key="i" class="sk-row"><div class="sk sk-ico"></div><div class="sk-col"><div class="sk sk-line"></div><div class="sk sk-line short"></div></div></div>
    </template>
  </div>
  <div v-else-if="error" class="state" role="alert">
    <i class="ti ti-cloud-off" aria-hidden="true"></i>
    <p>{{ error.message || 'حصلت مشكلة.' }}</p>
    <button v-if="error.status !== 404" class="btn btn-soft" @click="$emit('retry')">حاول تاني</button>
    <RouterLink v-else to="/" class="btn btn-soft">العودة للرئيسية</RouterLink>
  </div>
  <p v-else-if="empty" class="empty">{{ emptyText }}</p>
  <slot v-else />
</template>

<style scoped>
.state { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 48px 16px; text-align: center; color: var(--text-2); background: var(--surface); border-radius: var(--radius); }
.state .ti { font-size: 36px; color: var(--text-3); }
.skel { display: flex; flex-direction: column; gap: 10px; }
.skel.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
.sk { background: var(--surface-2); border-radius: 8px; animation: pulse 1.4s ease-in-out infinite; }
.sk-row { display: flex; gap: 14px; align-items: center; padding: 12px; border: 1px solid var(--border); border-radius: var(--radius); }
.sk-ico { width: 120px; aspect-ratio: 16/9; flex-shrink: 0; }
.sk-col { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.sk-line { height: 12px; }
.sk-line.short { width: 55%; }
.sk-card { display: flex; flex-direction: column; gap: 8px; }
.sk-thumb { aspect-ratio: 16/9; border-radius: 10px; }
.sk-hero { height: 220px; border-radius: 16px; margin-bottom: 8px; }
.page .sk-line { height: 16px; }
@keyframes pulse { 50% { opacity: 0.55; } }
@media (max-width: 560px) { .sk-ico { width: 88px; } }
</style>
