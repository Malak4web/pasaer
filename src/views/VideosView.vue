<script setup>
import { ref, computed, watch } from 'vue'
import { api } from '@/api'
import { usePaged } from '@/composables/useFetch'
import { useVideoStore } from '@/stores/videos'
import { toArDigits } from '@/utils/format'
import VideoCard from '@/components/VideoCard.vue'
import TopicChips from '@/components/TopicChips.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const vs = useVideoStore()

const topic = ref('')
const seriesId = ref('')
const length = ref('') // '' | 'short' | 'medium' | 'long'
const sort = ref('new')
const search = ref('')
const debouncedSearch = ref('')
const showSavedOnly = ref(false)

let searchTimer = null
watch(search, (val) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    debouncedSearch.value = val.trim()
  }, 300)
})

const list = usePaged(
  (page) =>
    api.videos({
      topic: topic.value,
      series: seriesId.value,
      length: length.value,
      sort: sort.value,
      q: debouncedSearch.value,
      page
    }),
  [topic, seriesId, length, sort, debouncedSearch]
)

const videoSeries = ref([])
watch(
  () => list.meta.value,
  () => {
    // حفظ قائمة السلاسل المرئية عند أول تحميل
  }
)

// قراءة السلاسل المرئية مباشرة من API مرة واحدة
api.videos({ page: 1 }).then((res) => {
  if (Array.isArray(res?.series)) videoSeries.value = res.series
}).catch(() => {})

function toggleSeries(id) {
  showSavedOnly.value = false
  seriesId.value = seriesId.value === String(id) ? '' : String(id)
}

const displayedItems = computed(() => {
  if (!showSavedOnly.value) return list.items.value
  const q = search.value.trim().toLowerCase()
  return q ? vs.saved.filter((v) => (v.title || '').toLowerCase().includes(q)) : vs.saved
})

const hasActiveFilters = computed(
  () => !!(topic.value || seriesId.value || length.value || debouncedSearch.value || showSavedOnly.value)
)

function clearFilters() {
  topic.value = ''
  seriesId.value = ''
  length.value = ''
  search.value = ''
  debouncedSearch.value = ''
  showSavedOnly.value = false
}
</script>

<template>
  <div class="page">
    <header class="page-head vhead">
      <div>
        <h1>المرئيات</h1>
        <p>مكتبة القناة المرئية مصنّفة حسب السلاسل والمواضيع مع تشغيل مباشر من يوتيوب.</p>
      </div>
      <button
        type="button"
        class="saved-toggle"
        :class="{ on: showSavedOnly }"
        @click="showSavedOnly = !showSavedOnly"
      >
        <i class="ti" :class="showSavedOnly ? 'ti-bookmark-filled' : 'ti-bookmark'" aria-hidden="true"></i>
        شاهد لاحقًا
        <span v-if="vs.saved.length" class="cnt">{{ toArDigits(vs.saved.length) }}</span>
      </button>
    </header>

    <!-- السلاسل المرئية -->
    <section v-if="videoSeries.length && !showSavedOnly" class="series-strip" aria-label="السلاسل المرئية">
      <button
        type="button"
        class="s-chip"
        :class="{ on: !seriesId }"
        @click="seriesId = ''"
      >
        <i class="ti ti-layout-grid" aria-hidden="true"></i>
        كل المرئيات
      </button>
      <button
        v-for="s in videoSeries"
        :key="s.id"
        type="button"
        class="s-chip"
        :class="{ on: seriesId === String(s.id) }"
        @click="toggleSeries(s.id)"
      >
        <i class="ti ti-playlist" aria-hidden="true"></i>
        <span>{{ s.title }}</span>
        <span class="s-cnt">{{ toArDigits(s.count) }}</span>
      </button>
    </section>

    <!-- شريط البحث والفلاتر -->
    <div class="v-controls">
      <div class="v-search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input v-model="search" type="search" placeholder="ابحث في المرئيات..." aria-label="ابحث في المرئيات" />
      </div>

      <div v-if="!showSavedOnly" class="v-filters">
        <div class="seg" role="group" aria-label="المدة">
          <button :class="{ on: length === '' }" @click="length = ''">الكل</button>
          <button :class="{ on: length === 'short' }" @click="length = 'short'">قصيرة</button>
          <button :class="{ on: length === 'medium' }" @click="length = 'medium'">متوسطة</button>
          <button :class="{ on: length === 'long' }" @click="length = 'long'">كاملة</button>
        </div>

        <div class="seg" role="group" aria-label="ترتيب">
          <button :class="{ on: sort === 'new' }" :aria-pressed="sort === 'new'" @click="sort = 'new'">الأحدث</button>
          <button :class="{ on: sort === 'popular' }" :aria-pressed="sort === 'popular'" @click="sort = 'popular'">الأكثر مشاهدة</button>
        </div>
      </div>
    </div>

    <div v-if="!showSavedOnly" class="toolbar">
      <TopicChips v-model="topic" />
      <button v-if="hasActiveFilters" type="button" class="clear-btn" @click="clearFilters">
        <i class="ti ti-x" aria-hidden="true"></i> مسح الفلاتر
      </button>
    </div>

    <StateView
      :loading="!showSavedOnly && list.loading.value"
      :error="!showSavedOnly ? list.error.value : null"
      :empty="!displayedItems.length"
      :empty-text="showSavedOnly ? 'قائمة «شاهد لاحقًا» فارغة. اضغط على أيقونة الحفظ في أي فيديو لإضافته هنا.' : 'لا توجد مقاطع مطابقة لهذه الفلاتر.'"
      skeleton="grid"
      @retry="list.reload"
    >
      <div class="grid-cards">
        <VideoCard v-for="v in displayedItems" :key="v.key || v.id" :video="v" />
      </div>
    </StateView>

    <LoadMore v-if="!showSavedOnly" :show="list.hasMore()" :loading="list.loadingMore.value" @more="list.more" />
  </div>
</template>

<style scoped>
.vhead { flex-direction: row; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
.saved-toggle {
  display: inline-flex; align-items: center; gap: 6px;
  height: 38px; padding: 0 14px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
  font-size: 14px; font-weight: 500; color: var(--text-2);
  transition: .15s var(--ease);
}
.saved-toggle:hover { border-color: var(--primary); color: var(--primary); }
.saved-toggle.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
.saved-toggle .cnt { background: rgba(0, 0, 0, 0.12); padding: 0 7px; border-radius: 999px; font-size: 12px; }

.series-strip {
  display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;
  scrollbar-width: none; scroll-snap-type: x proximity;
  margin-top: -10px;
}
.series-strip::-webkit-scrollbar { display: none; }
.s-chip {
  display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
  padding: 8px 13px; border-radius: 12px;
  background: var(--surface); border: 1px solid var(--border);
  font-size: 13.5px; font-weight: 500; color: var(--text-2);
  scroll-snap-align: start; transition: .15s var(--ease);
}
.s-chip:hover { border-color: var(--primary); color: var(--text); }
.s-chip.on { background: var(--primary-soft); border-color: var(--primary); color: var(--primary); font-weight: 600; }
.s-cnt { font-size: 11.5px; background: var(--bg); color: var(--text-3); padding: 1px 7px; border-radius: 999px; }
.s-chip.on .s-cnt { background: var(--primary); color: var(--on-primary); }

.v-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: -14px; }
.v-search {
  flex: 1; min-width: 210px;
  display: flex; align-items: center; gap: 8px;
  height: 38px; padding: 0 12px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
}
.v-search:focus-within { border-color: var(--primary); background: var(--bg); }
.v-search .ti { color: var(--text-3); font-size: 18px; }
.v-search input { flex: 1; min-width: 0; border: 0; background: none; outline: none; font-size: 14px; }
.v-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-top: -16px; margin-bottom: -10px; }
.toolbar > :first-child { min-width: 0; flex: 1; }
.clear-btn { font-size: 13px; color: var(--primary); display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; background: var(--primary-soft); }

.seg { display: flex; background: var(--surface); border-radius: 999px; padding: 3px; flex-shrink: 0; }
.seg button { height: 30px; padding: 0 11px; border-radius: 999px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--bg); color: var(--text); font-weight: 500; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08); }

@media (max-width: 900px) {
  .series-strip { margin-inline: -16px; padding: 0 16px 6px; }
  .v-filters { width: 100%; justify-content: space-between; }
}
</style>
