// عميل بسيط للـ API — VITE_API_URL فاضي في التطوير (بيمر من proxy الخاص بـ Vite)
const BASE = (import.meta.env.VITE_API_URL || '').replace(/\/$/, '') + '/api'

export class ApiError extends Error {
  constructor(status, message) {
    super(message)
    this.status = status
  }
}

async function request(path, { params, method = 'GET', signal } = {}) {
  const url = new URL(BASE + path, window.location.origin)
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, v)
  })

  let res
  try {
    res = await fetch(url, { method, signal, headers: { Accept: 'application/json' } })
  } catch (e) {
    if (e.name === 'AbortError') throw e
    throw new ApiError(0, 'تعذّر الاتصال بالسيرفر. تأكد من اتصالك بالإنترنت.')
  }

  if (res.status === 204) return null
  if (!res.ok) {
    throw new ApiError(res.status, res.status === 404 ? 'المحتوى غير موجود.' : 'حصلت مشكلة في السيرفر. حاول تاني.')
  }
  return res.json()
}

export const api = {
  site: () => request('/site'),
  home: () => request('/home'),
  feed: (params) => request('/feed', { params }),
  search: (q) => request('/search', { params: { q } }),

  videos: (params) => request('/videos', { params }),
  video: (id) => request(`/videos/${id}`),

  audios: (params) => request('/audios', { params }),
  audio: (id) => request(`/audios/${id}`),
  audioPlayed: (id) => request(`/audios/${id}/played`, { method: 'POST' }).catch(() => {}),

  lessons: (params) => request('/lessons', { params }),
  lesson: (id) => request(`/lessons/${id}`),

  posts: (params) => request('/posts', { params }),
  post: (id) => request(`/posts/${id}`),

  seriesList: () => request('/series'),
  series: (id) => request(`/series/${id}`),

  topic: (slug, params) => request(`/topics/${slug}`, { params })
}
