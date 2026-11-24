<script setup>
import { ref } from 'vue'
import { api } from '@/api'
import { usePaged } from '@/composables/useFetch'
import { usePlayerStore } from '@/stores/player'
import FeedItem from '@/components/FeedItem.vue'
import TopicChips from '@/components/TopicChips.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const player = usePlayerStore()
const topic = ref('')
const list = usePaged((page) => api.audios({ topic: topic.value, page }), [topic])
</script>

<template>
  <div class="page">
    <header class="page-head with-action">
      <div>
        <h1>الصوتيات</h1>
        <p>استمع وانت بتتصفّح، والتشغيل مش هيقف لو انتقلت لصفحة تانية.</p>
      </div>
      <button v-if="list.items.value.length" class="btn btn-primary" @click="player.play(list.items.value[0], list.items.value)">
        <i class="ti ti-player-play" aria-hidden="true"></i> تشغيل الكل
      </button>
    </header>
    <TopicChips v-model="topic" />
    <StateView
      :loading="list.loading.value"
      :error="list.error.value"
      :empty="!list.items.value.length"
      empty-text="لا توجد صوتيات في هذا الموضوع بعد."
      @retry="list.reload"
    >
      <div class="list">
        <FeedItem v-for="a in list.items.value" :key="a.key" :item="a" :queue="list.items.value" :show-badge="false" />
      </div>
    </StateView>
    <LoadMore :show="list.hasMore()" :loading="list.loadingMore.value" @more="list.more" />
  </div>
</template>

<style scoped>
.with-action { flex-direction: row; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.with-action > div { display: flex; flex-direction: column; gap: 6px; }
.page > :deep(.chips) { margin-bottom: -12px; }
</style>
