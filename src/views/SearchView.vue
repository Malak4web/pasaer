<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { typeMeta } from '@/utils/types'
import { toArDigits } from '@/utils/format'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'

const route = useRoute()
const type = ref('')
const q = computed(() => (route.query.q || '').toString().trim())
const tooShort = computed(() => q.value.length < 2)

const { data, loading, error, reload } = useFetch(() =>
  tooShort.value ? Promise.resolve({ data: [], meta: { total: 0 }, counts: {} }) : api.search(q.value)
)
const results = computed(() => data.value?.data || [])
const counts = computed(() => data.value?.counts || {})
const filtered = computed(() => results.value.filter((x) => !type.value || x.type === type.value))
const audioQueue = computed(() => filtered.value.filter((x) => x.type === 'audio'))
</script>

<template>
  <div class="page">
    <header class="page-head">
      <h1 v-if="q">نتائج البحث عن «{{ q }}»</h1>
      <h1 v-else>البحث</h1>
      <p v-if="tooShort">اكتب كلمتين على الأقل في خانة البحث بالأعلى.</p>
      <p v-else-if="!loading">{{ toArDigits(data?.meta?.total || 0) }} نتيجة</p>
    </header>

    <div v-if="results.length" class="tabs" role="group" aria-label="نوع المحتوى">
      <button :class="{ on: !type }" :aria-pressed="!type" @click="type = ''">الكل</button>
      <template v-for="(m, key) in typeMeta" :key="key">
        <button v-if="counts[key]" :class="{ on: type === key }" :aria-pressed="type === key" @click="type = key">
          {{ m.label }} <span>{{ toArDigits(counts[key]) }}</span>
        </button>
      </template>
    </div>

    <StateView
      v-if="!tooShort"
      :loading="loading"
      :error="error"
      :empty="!filtered.length"
      empty-text="لا توجد نتائج. جرّب كلمة أقصر أو تصفّح المواضيع من القائمة."
      @retry="reload"
    >
      <div class="list">
        <FeedItem v-for="it in filtered" :key="it.key" :item="it" :queue="audioQueue" />
      </div>
    </StateView>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; border-bottom: 1px solid var(--border); margin-bottom: -12px; overflow-x: auto; scrollbar-width: none; }
.tabs::-webkit-scrollbar { display: none; }
.tabs button {
  padding: 8px 12px;
  font-size: 14px;
  color: var(--text-2);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  white-space: nowrap;
}
.tabs button.on { color: var(--primary); border-color: var(--primary); font-weight: 500; }
.tabs span { font-size: 12px; color: var(--text-3); }
</style>
