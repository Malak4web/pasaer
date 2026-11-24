<script setup>
import { ref } from 'vue'
import { api } from '@/api'
import { usePaged } from '@/composables/useFetch'
import FeedItem from '@/components/FeedItem.vue'
import TopicChips from '@/components/TopicChips.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const topic = ref('')
const list = usePaged((page) => api.posts({ topic: topic.value, page }), [topic])
</script>

<template>
  <div class="page">
    <header class="page-head">
      <h1>المنشورات</h1>
      <p>خواطر ووقفات قصيرة تُقرأ في دقائق.</p>
    </header>
    <TopicChips v-model="topic" />
    <StateView
      :loading="list.loading.value"
      :error="list.error.value"
      :empty="!list.items.value.length"
      empty-text="لا توجد منشورات في هذا الموضوع بعد."
      @retry="list.reload"
    >
      <div class="list">
        <FeedItem v-for="p in list.items.value" :key="p.key" :item="p" :show-badge="false" />
      </div>
    </StateView>
    <LoadMore :show="list.hasMore()" :loading="list.loadingMore.value" @more="list.more" />
  </div>
</template>

<style scoped>
.page > :deep(.chips) { margin-bottom: -12px; }
</style>
