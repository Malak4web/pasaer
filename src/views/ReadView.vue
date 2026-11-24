<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '@/api'
import { useFetch } from '@/composables/useFetch'
import { usePageTitle } from '@/utils/usePageTitle'
import { date, toArDigits } from '@/utils/format'
import TypeBadge from '@/components/TypeBadge.vue'
import StateView from '@/components/StateView.vue'

const props = defineProps({ kind: { type: String, required: true } })
const route = useRoute()
const { data, loading, error, reload } = useFetch(() =>
  props.kind === 'lesson' ? api.lesson(route.params.id) : api.post(route.params.id)
)
const item = computed(() => data.value?.item)
usePageTitle(() => item.value?.title)

async function share() {
  const payload = { title: item.value.title, url: location.href }
  try {
    if (navigator.share) await navigator.share(payload)
    else await navigator.clipboard.writeText(location.href)
  } catch {
    /* user cancelled */
  }
}
</script>

<template>
  <div class="page read">
    <StateView :loading="loading" :error="error" skeleton="page" @retry="reload">
      <template v-if="item">
        <article>
          <header class="ahead">
            <TypeBadge :type="kind" />
            <RouterLink v-if="item.series" :to="`/series/${item.series.id}`" class="eyebrow">
              {{ item.series.title }}<template v-if="data.position"> · الدرس {{ toArDigits(data.position) }} من {{ toArDigits(data.total) }}</template>
            </RouterLink>
            <h1>{{ item.title }}</h1>
            <p class="meta">
              <template v-if="item.topic">{{ item.topic.name }} · </template>{{ date(item.date) }} · {{ toArDigits(item.readMinutes) }} دقائق قراءة
            </p>
          </header>
          <div class="prose">
            <p v-for="(para, i) in item.body" :key="i">{{ para }}</p>
          </div>
          <div class="tools">
            <button class="btn btn-soft" @click="share">
              <i class="ti ti-share" aria-hidden="true"></i> مشاركة
            </button>
          </div>
        </article>

        <nav v-if="data.prev || data.next" class="pager" aria-label="التنقل في السلسلة">
          <RouterLink v-if="data.prev" :to="`/lessons/${data.prev.id}`" class="pg">
            <span class="lbl">السابق</span>
            <span>{{ data.prev.title }}</span>
          </RouterLink>
          <span v-else></span>
          <RouterLink v-if="data.next" :to="`/lessons/${data.next.id}`" class="pg nx">
            <span class="lbl">التالي</span>
            <span>{{ data.next.title }}</span>
          </RouterLink>
        </nav>
      </template>
    </StateView>
  </div>
</template>

<style scoped>
.read { max-width: 760px; }
article { display: flex; flex-direction: column; gap: 24px; }
.ahead { display: flex; flex-direction: column; gap: 10px; padding-bottom: 20px; border-bottom: 1px solid var(--border); }
.eyebrow { font-size: 13px; color: var(--primary); font-weight: 500; }
h1 { font-size: 30px; line-height: 1.5; }
.meta { color: var(--text-3); font-size: 14px; }
.prose { display: flex; flex-direction: column; gap: 18px; font-size: 18px; line-height: 2; }
.tools { display: flex; gap: 8px; }
.pager { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.pg {
  display: flex; flex-direction: column; gap: 2px;
  border: 1px solid var(--border); border-radius: var(--radius);
  padding: 12px 14px; font-size: 15px;
}
.pg:hover { border-color: var(--primary); }
.pg.nx { text-align: left; }
.lbl { font-size: 12px; color: var(--text-3); }
@media (max-width: 600px) {
  h1 { font-size: 23px; }
  .prose { font-size: 17px; }
}
</style>
