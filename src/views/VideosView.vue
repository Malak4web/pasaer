<script setup>
import { ref, computed, watch } from 'vue'
import { api } from '@/api'
import { usePaged } from '@/composables/useFetch'
import { useSiteStore } from '@/stores/site'
import { useVideoStore } from '@/stores/videos'
import { toArDigits } from '@/utils/format'
import VideoCard from '@/components/VideoCard.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const store = useSiteStore()
const vs = useVideoStore()

const topic = ref('')
const seriesId = ref('')
const length = ref('')
const sort = ref('new')
const search = ref('')
const debouncedSearch = ref('')
const showSavedOnly = ref(false)

let searchTimer = null
watch(search, (val) => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => (debouncedSearch.value = val.trim()), 300)
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

const hasActiveFilters = computed(() => !!(topic.value || seriesId.value || length.value || debouncedSearch.value || showSavedOnly.value))

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
    <header class="v-top">
      <div>
        <h1>المرئيات</h1>
        <p>مكتبة القناة المرئية مصنّفة بالسلاسل والمواضيع.</p>
      </div>
      <div class="v-top-acts">
        <div class="v-search">
          <i class="ti ti-search" aria-hidden="true"></i>
          <input v-model="search" type="search" placeholder="ابحث في المرئيات..." aria-label="ابحث في المرئيات" />
        </div>
        <button type="button" class="saved-btn" :class="{ on: showSavedOnly }" @click="showSavedOnly = !showSavedOnly">
          <i class="ti" :class="showSavedOnly ? 'ti-bookmark-filled' : 'ti-bookmark'"></i>
          <span>شاهد لاحقًا</span>
          <span v-if="vs.saved.length" class="cnt">{{ toArDigits(vs.saved.length) }}</span>
        </button>
      </div>
    </header>

    <!-- شريط السلاسل المرئية -->
    <section v-if="videoSeries.length && !showSavedOnly" class="series-strip" aria-label="السلاسل المرئية">
      <button type="button" class="s-chip" :class="{ on: !seriesId }" @click="seriesId = ''">الكل</button>
      <button
        v-for="s in videoSeries"
        :key="s.id"
        type="button"
        class="s-chip"
        :class="{ on: seriesId === String(s.id) }"
        @click="toggleSeries(s.id)"
      >
        <span>{{ s.title }}</span>
        <span class="s-cnt">{{ toArDigits(s.count) }}</span>
      </button>
    </section>

    <!-- شريط فلاتر واحد مدمج ونظيف -->
    <div v-if="!showSavedOnly" class="filter-bar">
      <select v-model="topic" class="f-sel" aria-label="الموضوع">
        <option value="">كل المواضيع</option>
        <option v-for="t in store.topics" :key="t.slug" :value="t.slug">{{ t.name }}</option>
      </select>

      <select v-model="length" class="f-sel" aria-label="مدة المقطع">
        <option value="">كل المدد</option>
        <option value="short">مقاطع قصيرة (&lt; ١٠ د)</option>
        <option value="medium">متوسطة (١٠ – ٣٠ د)</option>
        <option value="long">دروس وخطب كاملة (&gt; ٣٠ د)</option>
      </select>

      <div class="seg" role="group" aria-label="ترتيب">
        <button :class="{ on: sort === 'new' }" @click="sort = 'new'">الأحدث</button>
        <button :class="{ on: sort === 'popular' }" @click="sort = 'popular'">الأكثر مشاهدة</button>
      </div>

      <button v-if="hasActiveFilters" type="button" class="clear-btn" @click="clearFilters">
        <i class="ti ti-x"></i> مسح
      </button>
    </div>

    <StateView
      :loading="!showSavedOnly && list.loading.value"
      :error="!showSavedOnly ? list.error.value : null"
      :empty="!displayedItems.length"
      :empty-text="showSavedOnly ? 'قائمة «شاهد لاحقًا» فارغة. اضغط على أيقونة الحفظ في أي فيديو لإضافته.' : 'لا توجد مقاطع مطابقة لهذه الفلاتر.'"
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
.v-top { display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 14px; }
.v-top h1 { font-size: 26px; margin: 0 0 4px; }
.v-top p { color: var(--text-2); font-size: 14.5px; margin: 0; }
.v-top-acts { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

.v-search {
  display: flex; align-items: center; gap: 8px;
  height: 40px; padding: 0 14px; border-radius: 999px;
  background: var(--card); border: 1px solid var(--border); min-width: 220px;
}
.v-search:focus-within { border-color: var(--primary); }
.v-search .ti { color: var(--text-3); font-size: 18px; }
.v-search input { flex: 1; min-width: 0; border: 0; background: none; outline: none; font-size: 14px; }

.saved-btn {
  display: inline-flex; align-items: center; gap: 6px;
  height: 40px; padding: 0 14px; border-radius: 999px;
  background: var(--card); border: 1px solid var(--border);
  font-size: 14px; font-weight: 500; color: var(--text-2);
}
.saved-btn.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
.saved-btn .cnt { background: rgba(0, 0, 0, 0.12); padding: 0 7px; border-radius: 999px; font-size: 12px; }

.series-strip {
  display: flex; gap: 8px; overflow-x: auto; padding-bottom: 2px;
  scrollbar-width: none; scroll-snap-type: x proximity; margin-top: -10px;
}
.series-strip::-webkit-scrollbar { display: none; }
.s-chip {
  display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
  height: 36px; padding: 0 13px; border-radius: 999px;
  background: var(--card); border: 1px solid var(--border);
  font-size: 13.5px; font-weight: 500; color: var(--text-2); scroll-snap-align: start;
}
.s-chip.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
.s-cnt { font-size: 11.5px; opacity: .75; }

.filter-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
  margin-top: -14px;
}
.f-sel {
  height: 36px; padding: 0 12px; border-radius: 999px;
  border: 1px solid var(--border); background: var(--card); color: var(--text-2);
  font-family: inherit; font-size: 13.5px;
}
.seg { display: inline-flex; background: var(--surface); border-radius: 999px; padding: 3px; margin-inline-start: auto; }
.seg button { height: 30px; padding: 0 12px; border-radius: 999px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--card); color: var(--text); font-weight: 600; box-shadow: var(--shadow-sm); }
.clear-btn { font-size: 13px; color: var(--primary); display: inline-flex; align-items: center; gap: 3px; padding: 0 10px; height: 34px; border-radius: 999px; background: var(--primary-soft); }

@media (max-width: 900px) {
  .v-top-acts { width: 100%; }
  .v-search { flex: 1; min-width: 0; }
  .series-strip { margin-inline: -16px; padding: 0 16px 4px; }
  .seg { margin-inline-start: 0; }
}
</style>
