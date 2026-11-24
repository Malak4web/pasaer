import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '@/api'

// إعدادات الموقع والمواضيع — بتتحمل مرة واحدة من لوحة التحكم
export const useSiteStore = defineStore('site', () => {
  const site = ref({
    name: 'بصائر',
    tagline: 'مرئيات وصوتيات ودروس ومنشورات دعوية في مكان واحد',
    speaker: '',
    youtubeChannelUrl: '',
    verse: { text: 'قَدْ جَاءَكُم بَصَائِرُ مِن رَّبِّكُمْ', ref: '' },
    topics: []
  })
  const loaded = ref(false)

  async function load() {
    try {
      site.value = await api.site()
      loaded.value = true
    } catch {
      /* نكمّل بالقيم الافتراضية */
    }
  }

  const topics = computed(() => site.value.topics || [])
  const topicBySlug = (slug) => topics.value.find((t) => t.slug === slug)

  return { site, topics, loaded, load, topicBySlug }
})
