import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

const PREFS_KEY = 'basaer:quran:prefs'
const BOOKMARKS_KEY = 'basaer:quran:bookmarks'
const LAST_READ_KEY = 'basaer:quran:last-read'

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

export const useQuranStore = defineStore('quran', () => {
  const savedPrefs = load(PREFS_KEY, {
    reciterId: 'ar.husary',
    tafsirId: 'ar.muyassar',
    viewMode: 'mushaf', // 'mushaf' | 'verses'
    theme: 'paper', // 'light' | 'paper' | 'dark'
    fontSize: 28,
    showTafsir: false,
    repeatCount: 1, // 1 | 3 | 5 | 0 (infinite)
    rate: 1
  })
  if (savedPrefs.reciterId === 'ar.mahermuaiqly' || savedPrefs.reciterId === 'ar.yasseraldossari') {
    savedPrefs.reciterId = 'ar.husary'
  }
  const prefs = ref(savedPrefs)

  // [{ key: '2:255', surah: 2, surahName: 'سورة البقرة', ayah: 255, text: '...', savedAt }]
  const bookmarks = ref(load(BOOKMARKS_KEY, []))

  // { surah: 1, surahName: 'سورة الفاتحة', ayah: 1, page: 1, updatedAt }
  const lastRead = ref(load(LAST_READ_KEY, null))

  watch(prefs, (v) => save(PREFS_KEY, v), { deep: true })

  function setLastRead(entry) {
    lastRead.value = { ...entry, updatedAt: Date.now() }
    save(LAST_READ_KEY, lastRead.value)
  }

  function isBookmarked(surah, ayah) {
    const key = `${surah}:${ayah}`
    return bookmarks.value.some((b) => b.key === key)
  }

  function toggleBookmark({ surah, surahName, ayah, text }) {
    const key = `${surah}:${ayah}`
    if (isBookmarked(surah, ayah)) {
      bookmarks.value = bookmarks.value.filter((b) => b.key !== key)
    } else {
      bookmarks.value = [
        { key, surah, surahName, ayah, text: String(text || '').slice(0, 140), savedAt: Date.now() },
        ...bookmarks.value
      ]
    }
    save(BOOKMARKS_KEY, bookmarks.value)
  }

  function removeBookmark(key) {
    bookmarks.value = bookmarks.value.filter((b) => b.key !== key)
    save(BOOKMARKS_KEY, bookmarks.value)
  }

  return { prefs, bookmarks, lastRead, setLastRead, isBookmarked, toggleBookmark, removeBookmark }
})
