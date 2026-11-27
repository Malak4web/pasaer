<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { useSiteStore } from '@/stores/site'
import { useVideoStore } from '@/stores/videos'
import { usePageTitle } from '@/utils/usePageTitle'
import { date, num, clock } from '@/utils/format'
import VideoThumb from '@/components/VideoThumb.vue'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'

const route = useRoute()
const store = useSiteStore()
const vs = useVideoStore()

const cinemaMode = ref(false)
const copied = ref(false)

const { data, loading, error, reload } = useFetch(() => api.video(route.params.id), [() => route.params.id])
const video = computed(() => data.value?.item)
const related = computed(() => data.value?.related || [])
usePageTitle(() => video.value?.title)

watch(video, (v) => {
  if (v?.id) vs.markWatched(v.id)
})

// استخراج الحلقة السابقة والتالية عند المشاهدة ضمن سلسلة
const seriesNav = computed(() => {
  if (!video.value?.series || !related.value.length) return { prev: null, next: null }
  const all = [...related.value, video.value].sort((a, b) => (a.seriesOrder || 0) - (b.seriesOrder || 0) || a.id - b.id)
  const idx = all.findIndex((x) => x.id === video.value.id)
  return {
    prev: idx > 0 ? all[idx - 1] : null,
    next: idx >= 0 && idx < all.length - 1 ? all[idx + 1] : null
  }
})

async function shareVideo() {
  const url = window.location.href
  const title = video.value?.title || 'فيديو من بصائر'
  if (navigator.share) {
    try {
      await navigator.share({ title, url })
      return
    } catch {}
  }
  try {
    await navigator.clipboard.writeText(url)
    copied.value = true
    setTimeout(() => (copied.value = false), 2200)
  } catch {}
}
</script>

<template>
  <div class="page">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <div v-if="video" class="layout" :class="{ cinema: cinemaMode }">
        <article class="main">
          <div class="player">
            <iframe
              v-if="video.youtubeId"
              :src="`https://www.youtube-nocookie.com/embed/${video.youtubeId}?rel=0&modestbranding=1`"
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
            <RouterLink v-if="video.series" :to="`/series/${video.series.id}`" class="tag series">{{ video.series.title }}</RouterLink>
            <span v-if="video.duration"><i class="ti ti-clock" aria-hidden="true"></i> {{ clock(video.duration) }}</span>
            <span>{{ num(video.views) }} مشاهدة</span>
            <span>{{ date(video.date) }}</span>
          </div>

          <div class="actions">
            <button
              type="button"
              class="btn"
              :class="vs.isSaved(video.id) ? 'btn-primary' : 'btn-soft'"
              @click="vs.toggleSave(video)"
            >
              <i class="ti" :class="vs.isSaved(video.id) ? 'ti-bookmark-filled' : 'ti-bookmark'" aria-hidden="true"></i>
              {{ vs.isSaved(video.id) ? 'محفوظ في شاهد لاحقًا' : 'شاهد لاحقًا' }}
            </button>

            <button type="button" class="btn btn-soft" @click="shareVideo">
              <i class="ti" :class="copied ? 'ti-check' : 'ti-share'" aria-hidden="true"></i>
              {{ copied ? 'تم نسخ الرابط' : 'مشاركة' }}
            </button>

            <button type="button" class="btn btn-soft hide-sm" @click="cinemaMode = !cinemaMode">
              <i class="ti" :class="cinemaMode ? 'ti-minimize' : 'ti-maximize'" aria-hidden="true"></i>
              {{ cinemaMode ? 'الوضع العادي' : 'وضع السينما' }}
            </button>

            <a
              v-if="video.youtubeId || store.site.youtubeChannelUrl"
              :href="video.youtubeId ? `https://www.youtube.com/watch?v=${video.youtubeId}` : store.site.youtubeChannelUrl"
              target="_blank"
              rel="noopener"
              class="btn btn-soft"
            >
              <i class="ti ti-brand-youtube" aria-hidden="true"></i> يوتيوب
            </a>
          </div>

          <!-- التنقل بين حلقات السلسلة -->
          <div v-if="seriesNav.prev || seriesNav.next" class="ep-nav">
            <RouterLink v-if="seriesNav.prev" :to="`/videos/${seriesNav.prev.id}`" class="ep-btn">
              <i class="ti ti-chevron-right" aria-hidden="true"></i>
              <span><small>الحلقة السابقة</small><strong>{{ seriesNav.prev.title }}</strong></span>
            </RouterLink>
            <RouterLink v-if="seriesNav.next" :to="`/videos/${seriesNav.next.id}`" class="ep-btn next">
              <span><small>الحلقة التالية</small><strong>{{ seriesNav.next.title }}</strong></span>
              <i class="ti ti-chevron-left" aria-hidden="true"></i>
            </RouterLink>
          </div>

          <p v-if="video.description" class="desc">{{ video.description }}</p>
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
.layout.cinema { grid-template-columns: 1fr; }
.main { display: flex; flex-direction: column; gap: 14px; }
.player { aspect-ratio: 16 / 9; border-radius: var(--radius); overflow: hidden; background: #000; box-shadow: 0 8px 26px rgba(0, 0, 0, 0.14); }
.layout.cinema .player { max-height: 76vh; }
.player iframe { width: 100%; height: 100%; border: 0; }
.noid { position: relative; width: 100%; height: 100%; }
.noid :deep(.thumb) { height: 100%; border-radius: 0; }
.noid p {
  position: absolute; bottom: 12px; inset-inline: 12px;
  background: var(--bg); color: var(--text-2); font-size: 13px; padding: 8px 12px; border-radius: 8px;
}
h1 { font-size: 22px; margin-top: 2px; line-height: 1.45; }
.meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 14px; color: var(--text-3); }
.tag { background: var(--primary-soft); color: var(--primary); padding: 2px 10px; border-radius: 999px; font-size: 13px; font-weight: 500; }
.tag.series { background: var(--surface); color: var(--text-2); border: 1px solid var(--border); }
.actions { display: flex; flex-wrap: wrap; gap: 8px; }
.desc {
  color: var(--text-2); white-space: pre-line;
  background: var(--surface); border: 1px solid var(--border);
  padding: 14px 16px; border-radius: var(--radius); font-size: 15px;
}

.ep-nav { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.ep-btn {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border);
  transition: border-color 0.15s var(--ease), background 0.15s var(--ease);
}
.ep-btn:hover { border-color: var(--primary); background: var(--primary-soft); }
.ep-btn.next { justify-content: space-between; text-align: start; }
.ep-btn .ti { font-size: 20px; color: var(--primary); flex-shrink: 0; }
.ep-btn span { display: flex; flex-direction: column; min-width: 0; }
.ep-btn small { font-size: 12px; color: var(--text-3); }
.ep-btn strong { font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.side { display: flex; flex-direction: column; gap: 12px; }
.side h2 { font-size: 16px; }
.side :deep(.lead), .side :deep(.thumb), .side :deep(.ico) { width: 96px; }
.side :deep(.excerpt) { display: none; }
.all { font-size: 14px; color: var(--primary); }

@media (max-width: 1000px) {
  .layout { grid-template-columns: 1fr; }
  .hide-sm { display: none; }
}
</style>
