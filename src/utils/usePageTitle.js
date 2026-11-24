import { watchEffect } from 'vue'
import { useSiteStore } from '@/stores/site'

// يضبط عنوان التبويب لصفحات التفاصيل من عنوان المحتوى
export function usePageTitle(getTitle) {
  const store = useSiteStore()
  watchEffect(() => {
    const t = getTitle()
    if (t) document.title = `${t} · ${store.site.name}`
  })
}
