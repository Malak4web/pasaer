import { ref, shallowRef, watch, toValue } from 'vue'

/**
 * تحميل بيانات من الـ API مع حالة تحميل وخطأ.
 * - loader: دالة بترجع Promise
 * - sources: (اختياري) قيم/refs لو اتغيرت يعيد التحميل (زي فلتر الموضوع)
 */
export function useFetch(loader, sources = []) {
  const data = shallowRef(null)
  const loading = ref(true)
  const error = ref(null)
  let token = 0

  async function run() {
    const mine = ++token
    loading.value = true
    error.value = null
    try {
      const result = await loader()
      if (mine === token) data.value = result
    } catch (e) {
      if (mine === token) error.value = e
    } finally {
      if (mine === token) loading.value = false
    }
  }

  if (sources.length) watch(() => sources.map((s) => toValue(s)), run)
  run()

  return { data, loading, error, reload: run }
}

/**
 * قائمة بترقيم صفحات و"تحميل المزيد".
 * loader(page) لازم يرجع { data: [], meta: { page, lastPage, total } }
 */
export function usePaged(loader, sources = []) {
  const items = shallowRef([])
  const meta = ref(null)
  const loading = ref(true)
  const loadingMore = ref(false)
  const error = ref(null)
  let token = 0

  async function load(page = 1) {
    const mine = ++token
    page === 1 ? (loading.value = true) : (loadingMore.value = true)
    error.value = null
    try {
      const res = await loader(page)
      if (mine !== token) return
      items.value = page === 1 ? res.data : [...items.value, ...res.data]
      meta.value = res.meta
    } catch (e) {
      if (mine === token) error.value = e
    } finally {
      if (mine === token) loading.value = loadingMore.value = false
    }
  }

  const hasMore = () => meta.value && meta.value.page < meta.value.lastPage
  const more = () => hasMore() && !loadingMore.value && load(meta.value.page + 1)

  if (sources.length) watch(() => sources.map((s) => toValue(s)), () => load(1))
  load(1)

  return { items, meta, loading, loadingMore, error, hasMore, more, reload: () => load(1) }
}
