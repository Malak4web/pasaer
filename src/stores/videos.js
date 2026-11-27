import { defineStore } from 'pinia'
import { ref } from 'vue'

const SAVED_KEY = 'basaer:videos:saved'
const WATCHED_KEY = 'basaer:videos:watched'

function load(key, fallback) {
  try {
    return JSON.parse(localStorage.getItem(key)) ?? fallback
  } catch {
    return fallback
  }
}

function save(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {}
}

export const useVideoStore = defineStore('videos', () => {
  // قائمة "شاهد لاحقًا" (تحفظ كائنات الفيديو المختصرة للعرض الفوري)
  const saved = ref(load(SAVED_KEY, []))
  // معرّفات الفيديوهات التي تمت مشاهدتها
  const watchedIds = ref(load(WATCHED_KEY, []))

  const isSaved = (id) => saved.value.some((v) => v.id === id)
  const isWatched = (id) => watchedIds.value.includes(id)

  function toggleSave(video) {
    if (!video?.id) return
    if (isSaved(video.id)) {
      saved.value = saved.value.filter((v) => v.id !== video.id)
    } else {
      saved.value = [
        {
          id: video.id,
          key: video.key || `video-${video.id}`,
          title: video.title,
          youtubeId: video.youtubeId,
          thumb: video.thumb,
          duration: video.duration,
          views: video.views,
          date: video.date,
          topic: video.topic,
          series: video.series
        },
        ...saved.value
      ]
    }
    save(SAVED_KEY, saved.value)
  }

  function markWatched(id) {
    if (!id || watchedIds.value.includes(id)) return
    watchedIds.value = [id, ...watchedIds.value].slice(0, 300)
    save(WATCHED_KEY, watchedIds.value)
  }

  return { saved, watchedIds, isSaved, isWatched, toggleSave, markWatched }
})
