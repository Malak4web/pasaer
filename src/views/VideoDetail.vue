<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { useSiteStore } from '@/stores/site'
import { usePageTitle } from '@/utils/usePageTitle'
import { date, num } from '@/utils/format'
import VideoThumb from '@/components/VideoThumb.vue'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'

const route = useRoute()
const store = useSiteStore()
const { data, loading, error, reload } = useFetch(() => api.video(route.params.id))
const video = computed(() => data.value?.item)
const related = computed(() => data.value?.related || [])
usePageTitle(() => video.value?.title)
</script>

<template>
  <div class="page">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <div v-if="video" class="layout">
        <article class="main">
          <div class="player">
            <iframe
              v-if="video.youtubeId"
              :src="`https://www.youtube-nocookie.com/embed/${video.youtubeId}?rel=0`"
              :title="video.title"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
            ></iframe>
            <div v-else class="noid">
              <VideoThumb :video="video" />
              <p>المقطع ده لسه ما اتربطش برابط يوتيوب من لوحة التحكم.</p>
            </div>
          </div>
          <h1>{{ video.title }}</h1>
          <div class="meta">
            <RouterLink v-if="video.topic" :to="`/topics/${video.topic.slug}`" class="tag">{{ video.topic.name }}</RouterLink>
            <span>{{ num(video.views) }} مشاهدة</span>
            <span>{{ date(video.date) }}</span>
          </div>
          <p v-if="video.description" class="desc">{{ video.description }}</p>
          <div class="actions">
            <a
              v-if="video.youtubeId || store.site.youtubeChannelUrl"
              :href="video.youtubeId ? `https://www.youtube.com/watch?v=${video.youtubeId}` : store.site.youtubeChannelUrl"
              target="_blank"
              rel="noopener"
              class="btn btn-soft"
            >
              <i class="ti ti-brand-youtube" aria-hidden="true"></i> افتح على يوتيوب
            </a>
          </div>
        </article>

        <aside v-if="related.length" class="side">
          <h2>{{ video.series ? `من سلسلة ${video.series.title}` : 'مقاطع ذات صلة' }}</h2>
          <div class="list">
            <FeedItem v-for="r in related" :key="r.key" :item="r" :show-badge="false" />
          </div>
          <RouterLink v-if="video.series" :to="`/series/${video.series.id}`" class="all">عرض السلسلة كاملة ←</RouterLink>
        </aside>
      </div>
    </StateView>
  </div>
</template>

<style scoped>
.layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 28px; align-items: start; }
.main { display: flex; flex-direction: column; gap: 12px; }
.player { aspect-ratio: 16 / 9; border-radius: var(--radius); overflow: hidden; background: #000; }
.player iframe { width: 100%; height: 100%; border: 0; }
.noid { position: relative; width: 100%; height: 100%; }
.noid :deep(.thumb) { height: 100%; border-radius: 0; }
.noid p {
  position: absolute;
  bottom: 12px;
  inset-inline: 12px;
  background: var(--bg);
  color: var(--text-2);
  font-size: 13px;
  padding: 8px 12px;
  border-radius: 8px;
}
h1 { font-size: 22px; margin-top: 4px; }
.meta { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; font-size: 14px; color: var(--text-3); }
.tag { background: var(--primary-soft); color: var(--primary); padding: 2px 10px; border-radius: 999px; font-size: 13px; }
.desc { color: var(--text-2); white-space: pre-line; }
.side { display: flex; flex-direction: column; gap: 12px; }
.side h2 { font-size: 16px; }
.side :deep(.lead), .side :deep(.thumb), .side :deep(.ico) { width: 96px; }
.side :deep(.excerpt) { display: none; }
.all { font-size: 14px; color: var(--primary); }

@media (max-width: 1000px) {
  .layout { grid-template-columns: 1fr; }
}
</style>
