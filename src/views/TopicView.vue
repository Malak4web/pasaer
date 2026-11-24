<script setup>
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePageTitle } from '@/utils/usePageTitle'
import { toArDigits } from '@/utils/format'
import FeedItem from '@/components/FeedItem.vue'
import SeriesCard from '@/components/SeriesCard.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const route = useRoute()
const { data, loading, error, reload } = useFetch(() => api.topic(route.params.id))
const topic = computed(() => data.value?.topic)

// صفحات إضافية من نفس الموضوع
const extra = ref([])
const page = ref(1)
const loadingMore = ref(false)
const items = computed(() => [...(data.value?.data || []), ...extra.value])
const hasMore = computed(() => data.value && page.value < data.value.meta.lastPage)
async function more() {
  loadingMore.value = true
  try {
    const res = await api.topic(route.params.id, { page: page.value + 1 })
    extra.value = [...extra.value, ...res.data]
    page.value++
  } finally {
    loadingMore.value = false
  }
}

const audioQueue = computed(() => items.value.filter((x) => x.type === 'audio'))
usePageTitle(() => topic.value?.name)
</script>

<template>
  <div class="page">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <template v-if="topic">
        <header class="page-head">
          <h1><i class="ti" :class="topic.icon" aria-hidden="true"></i> {{ topic.name }}</h1>
          <p>{{ toArDigits(data.meta.total) }} مادة في هذا الموضوع.</p>
        </header>
        <section v-if="data.series.length" class="section">
          <div class="section-head"><h2>سلاسل</h2></div>
          <div class="grid-cards">
            <SeriesCard v-for="s in data.series" :key="s.id" :series="s" />
          </div>
        </section>
        <section class="section">
          <div class="section-head"><h2>كل المحتوى</h2></div>
          <div v-if="items.length" class="list">
            <FeedItem v-for="it in items" :key="it.key" :item="it" :queue="audioQueue" />
          </div>
          <p v-else class="empty">لا يوجد محتوى في هذا الموضوع بعد.</p>
          <LoadMore :show="hasMore" :loading="loadingMore" @more="more" />
        </section>
      </template>
    </StateView>
  </div>
</template>

<style scoped>
h1 { display: flex; align-items: center; gap: 8px; }
h1 .ti { color: var(--primary); }
</style>
