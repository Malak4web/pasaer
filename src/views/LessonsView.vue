<script setup>
import { api } from '@/api'
import { useFetch, usePaged } from '@/composables/useFetch'
import SeriesCard from '@/components/SeriesCard.vue'
import FeedItem from '@/components/FeedItem.vue'
import StateView from '@/components/StateView.vue'
import LoadMore from '@/components/LoadMore.vue'

const series = useFetch(() => api.seriesList())
const lessons = usePaged((page) => api.lessons({ page }))
</script>

<template>
  <div class="page">
    <header class="page-head">
      <h1>الدروس والسلاسل</h1>
      <p>دروس متسلسلة مرئية وصوتية ومكتوبة. ابدأ سلسلة وكمّلها بالترتيب.</p>
    </header>

    <section class="section" aria-labelledby="s-title">
      <div class="section-head"><h2 id="s-title">السلاسل</h2></div>
      <StateView
        :loading="series.loading.value"
        :error="series.error.value"
        :empty="!series.data.value?.data?.length"
        empty-text="لا توجد سلاسل بعد."
        skeleton="grid"
        @retry="series.reload"
      >
        <div class="grid-cards">
          <SeriesCard v-for="s in series.data.value.data" :key="s.id" :series="s" />
        </div>
      </StateView>
    </section>

    <section class="section" aria-labelledby="l-title">
      <div class="section-head"><h2 id="l-title">أحدث الدروس المكتوبة</h2></div>
      <StateView
        :loading="lessons.loading.value"
        :error="lessons.error.value"
        :empty="!lessons.items.value.length"
        empty-text="لا توجد دروس مكتوبة بعد."
        @retry="lessons.reload"
      >
        <div class="list">
          <FeedItem v-for="l in lessons.items.value" :key="l.key" :item="l" :show-badge="false" />
        </div>
      </StateView>
      <LoadMore :show="lessons.hasMore()" :loading="lessons.loadingMore.value" @more="lessons.more" />
    </section>
  </div>
</template>
