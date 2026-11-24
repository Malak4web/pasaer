<script setup>
import { ref } from 'vue'
import { api } from '@/api'
import { usePaged } from '@/composables/useFetch'
import VideoCard from '@/components/VideoCard.vue'
import TopicChips from '@/components/TopicChips.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const topic = ref('')
const sort = ref('new')
const list = usePaged((page) => api.videos({ topic: topic.value, sort: sort.value, page }), [topic, sort])
</script>

<template>
  <div class="page">
    <header class="page-head">
      <h1>المرئيات</h1>
      <p>كل مقاطع القناة مرتّبة حسب الموضوع.</p>
    </header>
    <div class="toolbar">
      <TopicChips v-model="topic" />
      <div class="seg" role="group" aria-label="ترتيب">
        <button :class="{ on: sort === 'new' }" :aria-pressed="sort === 'new'" @click="sort = 'new'">الأحدث</button>
        <button :class="{ on: sort === 'popular' }" :aria-pressed="sort === 'popular'" @click="sort = 'popular'">الأكثر مشاهدة</button>
      </div>
    </div>
    <StateView
      :loading="list.loading.value"
      :error="list.error.value"
      :empty="!list.items.value.length"
      empty-text="لا توجد مقاطع في هذا الموضوع بعد."
      skeleton="grid"
      @retry="list.reload"
    >
      <div class="grid-cards">
        <VideoCard v-for="v in list.items.value" :key="v.key" :video="v" />
      </div>
    </StateView>
    <LoadMore :show="list.hasMore()" :loading="list.loadingMore.value" @more="list.more" />
  </div>
</template>

<style scoped>
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: -12px; }
.toolbar > :first-child { min-width: 0; flex: 1; }
.seg { display: flex; background: var(--surface); border-radius: 999px; padding: 3px; flex-shrink: 0; }
.seg button { height: 30px; padding: 0 12px; border-radius: 999px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--bg); color: var(--text); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08); }
</style>
