import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { api } from '@/api'

const STORAGE_KEY = 'basaer:progress'

function loadProgress() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {}
  } catch {
    return {}
  }
}

function saveProgress(p) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(p))
  } catch {
    /* storage unavailable — progress stays in memory */
  }
}

export const usePlayerStore = defineStore('player', () => {
  const track = ref(null)
  const queue = ref([])
  const playing = ref(false)
  const position = ref(0)
  const duration = ref(0)
  const rate = ref(1)
  const expanded = ref(false)
  // { [trackId]: { position, duration, updatedAt, track } }
  const progress = ref(loadProgress())

  const audio = typeof Audio !== 'undefined' ? new Audio() : null
  let demoTimer = null
  const isDemo = computed(() => !!track.value && !track.value.src)

  // ---------- audio element wiring ----------
  if (audio) {
    audio.preload = 'metadata'
    audio.addEventListener('timeupdate', () => (position.value = audio.currentTime))
    audio.addEventListener('loadedmetadata', () => {
      if (isFinite(audio.duration)) duration.value = audio.duration
    })
    audio.addEventListener('play', () => (playing.value = true))
    audio.addEventListener('pause', () => (playing.value = false))
    audio.addEventListener('ended', next)
  }

  // ---------- demo mode (no src yet): simulate playback ----------
  function startDemo() {
    stopDemo()
    playing.value = true
    demoTimer = setInterval(() => {
      position.value += 1 * rate.value
      if (position.value >= duration.value) next()
    }, 1000)
  }
  function stopDemo() {
    clearInterval(demoTimer)
    demoTimer = null
  }

  // ---------- actions ----------
  function play(t, list) {
    if (list) queue.value = list
    if (track.value?.id === t.id) return resume()

    pause()
    track.value = t
    duration.value = t.duration || 0
    const saved = progress.value[t.id]
    position.value = saved && saved.position < (saved.duration || Infinity) - 5 ? saved.position : 0

    if (t.src && audio) {
      api.audioPlayed(t.id) // إحصائية التشغيل في لوحة التحكم
      audio.src = t.src
      audio.currentTime = position.value
      audio.playbackRate = rate.value
      audio.play().catch(() => (playing.value = false))
    } else {
      startDemo()
    }
  }

  function resume() {
    if (!track.value) return
    if (isDemo.value) startDemo()
    else audio?.play().catch(() => {})
  }

  function pause() {
    if (isDemo.value || !audio) {
      stopDemo()
      playing.value = false
    } else {
      audio.pause()
    }
  }

  function toggle() {
    playing.value ? pause() : resume()
  }

  function seek(sec) {
    position.value = Math.min(Math.max(0, sec), duration.value || 0)
    if (!isDemo.value && audio) audio.currentTime = position.value
  }

  const skip = (delta) => seek(position.value + delta)

  function indexInQueue() {
    return queue.value.findIndex((x) => x.id === track.value?.id)
  }
  function next() {
    const i = indexInQueue()
    if (i > -1 && i < queue.value.length - 1) play(queue.value[i + 1])
    else {
      pause()
      position.value = duration.value
    }
  }
  function prev() {
    if (position.value > 5) return seek(0)
    const i = indexInQueue()
    if (i > 0) play(queue.value[i - 1])
  }

  function setRate(r) {
    rate.value = r
    if (audio) audio.playbackRate = r
  }

  function cycleRate() {
    const rates = [1, 1.25, 1.5, 1.75, 2, 0.75]
    setRate(rates[(rates.indexOf(rate.value) + 1) % rates.length])
  }

  // ---------- persist listening progress (throttled) ----------
  let lastSaved = 0
  watch(position, (p) => {
    if (!track.value) return
    const now = Date.now()
    if (now - lastSaved < 3000 && playing.value) return
    lastSaved = now
    const { id, title, duration: d, series, topic, src } = track.value
    progress.value = {
      ...progress.value,
      [id]: { position: p, duration: duration.value || d, updatedAt: now, track: { id, title, duration: d, series, topic, src } }
    }
    saveProgress(progress.value)
  })

  const continueListening = computed(() =>
    Object.values(progress.value)
      .filter((x) => x.position > 10 && x.position < x.duration - 10)
      .sort((a, b) => b.updatedAt - a.updatedAt)
  )

  const isCurrent = (id) => track.value?.id === id
  const progressOf = (id) => {
    const p = progress.value[id]
    return p ? Math.min(1, p.position / (p.duration || 1)) : 0
  }

  return {
    track, queue, playing, position, duration, rate, expanded, isDemo,
    continueListening,
    play, pause, resume, toggle, seek, skip, next, prev, setRate, cycleRate,
    isCurrent, progressOf
  }
})
