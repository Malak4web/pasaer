<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuranStore } from '@/stores/quran'
import { usePlayerStore } from '@/stores/player'
import { toArDigits } from '@/utils/format'
import {
  QURAN_CDN,
  READING_RIWAYAT,
  QURAN_RECITERS,
  QURAN_TAFSIRS,
  QURAN_JUZS,
  JUZ_START_PAGES,
  EXCLUDED_RECITER_KEYWORDS,
  surahMp3Url,
  normalizeAr
} from '@/data/quran'

// كاش دائم في الذاكرة لتقليب الصفحات والروايات في ٠ مللي ثانية بدون أي تحميل
let cachedInfo = null
const riwayahCache = new Map() // riwayahId -> Map("chapter:verse", text)
const tafsirCache = new Map() // `${page}:${tafsirId}` -> Map("chapter:verse", text)

const route = useRoute()
const router = useRouter()
const qs = useQuranStore()
const globalPlayer = usePlayerStore()

const API_BASE = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')

const SECTIONS = [
  { id: 'read', label: 'مصحف القراءة والقراءات', icon: 'ti-book-2' },
  { id: 'qiraat', label: 'صوتيات القراءات (٢٠)', icon: 'ti-headphones' },
  { id: 'downloads', label: 'التحميل (آيات وأجزاء)', icon: 'ti-download' },
  { id: 'surahs', label: 'السور (١١٤)', icon: 'ti-list-numbers' },
  { id: 'juzs', label: 'الأجزاء (٣٠)', icon: 'ti-books' },
  { id: 'search', label: 'بحث وعلامات', icon: 'ti-search' }
]

const activeSection = ref(SECTIONS.some((s) => s.id === route.query.tab) ? route.query.tab : 'read')
function setSection(id) {
  activeSection.value = id
  router.replace({ query: { ...route.query, tab: id } })
  if (id === 'qiraat' && !riwayatAudio.value.length) loadRiwayatAudio()
}

/* ==================== 1. تحميل فهرس المصحف + الرواية مرة واحدة فقط ==================== */
const surahs = ref([]) // [{ number, name, englishName, revelationType, numberOfAyahs, startPage, globalStart }]
const pagesMeta = ref({}) // { [1..604]: [{ key, number, numberInSurah, surahNumber, surahName, revelationType, juz }] }
const activeRiwayahMap = ref(new Map()) // "chapter:verse" -> text
const initLoading = ref(false)
const riwayahSwitching = ref(false)
const initError = ref('')

const surahSearch = ref('')
const revFilter = ref('all')

function buildMetadata(info) {
  const chList = []
  const pMap = {}
  let globalIdx = 0

  for (const ch of info.chapters || []) {
    const startPage = ch.verses?.[0]?.page || 1
    const globalStart = globalIdx + 1
    const revType = ch.revelation === 'Mecca' ? 'Meccan' : 'Medinan'
    chList.push({
      number: ch.chapter,
      name: ch.arabicname,
      englishName: ch.name,
      revelationType: revType,
      numberOfAyahs: (ch.verses || []).length,
      startPage,
      globalStart
    })
    for (const v of ch.verses || []) {
      globalIdx += 1
      const p = v.page || 1
      if (!pMap[p]) pMap[p] = []
      pMap[p].push({
        key: `${ch.chapter}:${v.verse}`,
        number: globalIdx,
        numberInSurah: v.verse,
        surahNumber: ch.chapter,
        surahName: ch.arabicname,
        revelationType: revType,
        juz: v.juz || 1
      })
    }
  }
  surahs.value = chList
  pagesMeta.value = pMap
}

async function ensureRiwayahLoaded(riwayahId) {
  if (riwayahCache.has(riwayahId)) {
    activeRiwayahMap.value = riwayahCache.get(riwayahId)
    return
  }
  riwayahSwitching.value = true
  try {
    const res = await fetch(`${QURAN_CDN}/editions/${riwayahId}.min.json`)
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()
    const map = new Map()
    for (const v of json.quran || []) {
      map.set(`${v.chapter}:${v.verse}`, v.text)
    }
    riwayahCache.set(riwayahId, map)
    activeRiwayahMap.value = map
  } finally {
    riwayahSwitching.value = false
  }
}

async function initMushaf() {
  initError.value = ''
  if (!cachedInfo) {
    initLoading.value = true
    try {
      const [infoRes] = await Promise.all([
        fetch(`${QURAN_CDN}/info.json`),
        ensureRiwayahLoaded(qs.prefs.riwayahId || 'ara-quranuthmanihaf')
      ])
      if (!infoRes.ok) throw new Error('HTTP ' + infoRes.status)
      cachedInfo = await infoRes.json()
      buildMetadata(cachedInfo)
    } catch {
      initError.value = 'تعذّر تحميل المصحف الآن. تأكد من الاتصال بالإنترنت وحاول مرة أخرى.'
    } finally {
      initLoading.value = false
    }
  } else {
    buildMetadata(cachedInfo)
    await ensureRiwayahLoaded(qs.prefs.riwayahId || 'ara-quranuthmanihaf')
  }
}

watch(() => qs.prefs.riwayahId, async (newId) => {
  if (!newId) return
  try {
    await ensureRiwayahLoaded(newId)
  } catch {}
})

const filteredSurahs = computed(() => {
  const q = normalizeAr(surahSearch.value)
  return surahs.value.filter((s) => {
    if (revFilter.value !== 'all' && s.revelationType !== revFilter.value) return false
    if (!q) return true
    return (
      normalizeAr(s.name).includes(q) ||
      normalizeAr(s.englishName).includes(q) ||
      String(s.number) === q
    )
  })
})

function surahByNum(num) {
  return surahs.value.find((s) => s.number === num)
}

/* ==================== 2. عرض الصفحة الفوري (0ms — بدون أي تحميل!) ==================== */
const currentPage = ref(Math.min(604, Math.max(1, Number(route.query.page) || qs.lastRead?.page || 1)))
const pageInput = ref(currentPage.value)
const selectedAyahKey = ref(null)
const copiedAyah = ref(null)
const pageTafsirMap = ref(new Map())

function stripLeadingBasmala(text, surahNumber, ayahInSurah) {
  if (surahNumber === 1 || surahNumber === 9 || ayahInSurah !== 1) return text
  return text.replace(/^بِسْمِ\s+[^\s]+\s+[^\s]+\s+[^\s]+\s*/u, (m) => (m.includes('حِيم') ? '' : m)).trim() || text
}

const pageAyahs = computed(() => {
  const metaList = pagesMeta.value[currentPage.value] || []
  const rMap = activeRiwayahMap.value
  const tMap = pageTafsirMap.value
  const recId = qs.prefs.reciterId || 'ar.husary'

  return metaList.map((m) => {
    const rawText = rMap.get(m.key) || ''
    return {
      ...m,
      text: stripLeadingBasmala(rawText, m.surahNumber, m.numberInSurah),
      audio: `https://cdn.islamic.network/quran/audio/128/${recId}/${m.number}.mp3`,
      tafsir: tMap.get(m.key) || ''
    }
  })
})

const pageSurahs = computed(() => {
  const names = []
  for (const a of pageAyahs.value) {
    if (a.surahName && !names.includes(a.surahName)) names.push(a.surahName)
  }
  return names
})

const pageJuz = computed(() => pageAyahs.value[0]?.juz || 1)

const pageBlocks = computed(() => {
  const blocks = []
  let cur = null
  for (let idx = 0; idx < pageAyahs.value.length; idx++) {
    const a = pageAyahs.value[idx]
    if (!cur || cur.surahNumber !== a.surahNumber) {
      cur = {
        surahNumber: a.surahNumber,
        surahName: a.surahName,
        revelationType: a.revelationType,
        startsAtOne: a.numberInSurah === 1,
        items: []
      }
      blocks.push(cur)
    }
    cur.items.push({ ...a, pageIdx: idx })
  }
  return blocks
})

// تحميل التفسير في الخلفية عند الحاجة فقط (لا يعطّل تقليب الصفحات أبدًا)
async function loadPageTafsirIfNeeded(pageNum) {
  const tafId = qs.prefs.tafsirId || 'ar.muyassar'
  const cacheKey = `${pageNum}:${tafId}`
  if (tafsirCache.has(cacheKey)) {
    pageTafsirMap.value = tafsirCache.get(cacheKey)
    return
  }
  try {
    const res = await fetch(`https://api.alquran.cloud/v1/page/${pageNum}/${tafId}`)
    if (!res.ok) return
    const json = await res.json()
    const m = new Map()
    for (const a of json.data?.ayahs || []) {
      m.set(`${a.surah?.number}:${a.numberInSurah}`, a.text || '')
    }
    tafsirCache.set(cacheKey, m)
    if (currentPage.value === pageNum && qs.prefs.tafsirId === tafId) {
      pageTafsirMap.value = m
    }
  } catch {}
}

function goToPage(p, highlightKey = null, autoPlay = false) {
  const target = Math.min(604, Math.max(1, Number(p) || 1))
  stopRecitation()
  currentPage.value = target
  pageInput.value = target
  selectedAyahKey.value = highlightKey
  activeSection.value = 'read'
  router.replace({ query: { tab: 'read', page: target } })

  const first = pagesMeta.value[target]?.[0]
  if (first) {
    qs.setLastRead({ surah: first.surahNumber, surahName: first.surahName, ayah: first.numberInSurah, page: target })
  }

  if (qs.prefs.showTafsir || highlightKey) {
    loadPageTafsirIfNeeded(target)
  }

  nextTick(() => {
    if (highlightKey) {
      const el = document.getElementById(`ayah-${highlightKey}`)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    } else {
      window.scrollTo({ top: 0, behavior: 'instant' })
    }
    if (autoPlay && pageAyahs.value.length) playAyahAt(0)
  })
}

function openSurahPage(surahNum, ayahNum = 1) {
  const s = surahByNum(surahNum)
  if (!s) return
  if (ayahNum <= 1) {
    goToPage(s.startPage)
    return
  }
  // البحث الفوري في الذاكرة عن صفحة الآية المطلوبة (0ms)
  const key = `${surahNum}:${ayahNum}`
  for (let p = s.startPage; p <= 604; p++) {
    if ((pagesMeta.value[p] || []).some((m) => m.key === key)) {
      goToPage(p, key)
      return
    }
  }
  goToPage(s.startPage)
}

function openJuzPage(juzNum) {
  goToPage(JUZ_START_PAGES[juzNum - 1] || 1)
}

watch(() => [qs.prefs.showTafsir, qs.prefs.tafsirId, selectedAyahKey.value], () => {
  if (qs.prefs.showTafsir || selectedAyahKey.value) {
    loadPageTafsirIfNeeded(currentPage.value)
  }
})

// دعم السحب باللمس يمينًا ويسارًا لتقليب الصفحات على الموبايل
let touchStartX = 0
let touchStartY = 0
function onTouchStart(e) {
  touchStartX = e.changedTouches[0].clientX
  touchStartY = e.changedTouches[0].clientY
}
function onTouchEnd(e) {
  const dx = e.changedTouches[0].clientX - touchStartX
  const dy = e.changedTouches[0].clientY - touchStartY
  if (Math.abs(dx) > 65 && Math.abs(dx) > Math.abs(dy) * 1.4) {
    // في العربي RTL: السحب لليمين يفتح الصفحة التالية، ولليسار الصفحة السابقة
    if (dx > 0 && currentPage.value < 604) goToPage(currentPage.value + 1)
    else if (dx < 0 && currentPage.value > 1) goToPage(currentPage.value - 1)
  }
}

/* ==================== 3. التلاوة المتزامنة آية بآية مع قلب الصفحة تلقائيًا ==================== */
const ayahAudio = typeof Audio !== 'undefined' ? new Audio() : null
const playingAyahIdx = ref(-1)
const isReciting = ref(false)
let repeatDone = 0

if (ayahAudio) {
  ayahAudio.addEventListener('play', () => {
    isReciting.value = true
    if (globalPlayer.playing) globalPlayer.pause()
  })
  ayahAudio.addEventListener('pause', () => {
    isReciting.value = false
  })
  ayahAudio.addEventListener('ended', () => {
    const rep = qs.prefs.repeatCount
    if (rep === 0) {
      ayahAudio.currentTime = 0
      ayahAudio.play().catch(() => {})
      return
    }
    repeatDone += 1
    if (repeatDone < rep) {
      ayahAudio.currentTime = 0
      ayahAudio.play().catch(() => {})
      return
    }
    repeatDone = 0
    if (playingAyahIdx.value + 1 < pageAyahs.value.length) {
      playAyahAt(playingAyahIdx.value + 1)
    } else if (currentPage.value < 604) {
      goToPage(currentPage.value + 1, null, true)
    } else {
      isReciting.value = false
      playingAyahIdx.value = -1
    }
  })
}

function playAyahAt(idx) {
  const a = pageAyahs.value[idx]
  if (!ayahAudio || !a?.audio) return
  playingAyahIdx.value = idx
  repeatDone = 0
  ayahAudio.src = a.audio
  ayahAudio.playbackRate = qs.prefs.rate || 1
  ayahAudio.play().catch(() => (isReciting.value = false))
  qs.setLastRead({ surah: a.surahNumber, surahName: a.surahName, ayah: a.numberInSurah, page: currentPage.value })
  nextTick(() => {
    const el = document.getElementById(`ayah-${a.key}`)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
  })
}

function toggleRecitation() {
  if (!ayahAudio || !pageAyahs.value.length) return
  if (isReciting.value) ayahAudio.pause()
  else if (playingAyahIdx.value >= 0) ayahAudio.play().catch(() => {})
  else playAyahAt(0)
}

function stopRecitation() {
  if (ayahAudio) {
    ayahAudio.pause()
    ayahAudio.currentTime = 0
  }
  isReciting.value = false
  playingAyahIdx.value = -1
  repeatDone = 0
}

function cycleRepeat() {
  const opts = [1, 3, 5, 0]
  qs.prefs.repeatCount = opts[(opts.indexOf(qs.prefs.repeatCount) + 1) % opts.length]
}

function cycleRate() {
  const opts = [1, 1.25, 1.5, 0.75]
  qs.prefs.rate = opts[(opts.indexOf(qs.prefs.rate) + 1) % opts.length]
  if (ayahAudio) ayahAudio.playbackRate = qs.prefs.rate
}

const selectedAyahObj = computed(() =>
  selectedAyahKey.value ? pageAyahs.value.find((a) => a.key === selectedAyahKey.value) : null
)

async function copyAyah(a) {
  const txt = `﴿${a.text}﴾ [${a.surahName}: ${toArDigits(a.numberInSurah)}]`
  try {
    await navigator.clipboard.writeText(txt)
    copiedAyah.value = a.key
    setTimeout(() => (copiedAyah.value = null), 1800)
  } catch {}
}

async function shareAyah(a) {
  const txt = `﴿${a.text}﴾\n— ${a.surahName} (آية ${toArDigits(a.numberInSurah)})`
  if (navigator.share) {
    try { await navigator.share({ title: a.surahName, text: txt, url: window.location.href }); return } catch {}
  }
  copyAyah(a)
}

/* ==================== 4. صوتيات القراءات والروايات (20 رواية) ==================== */
const riwayatAudio = ref([])
const selectedRewayaId = ref(2)
const qiraatReciters = ref([])
const selectedQiraatReciter = ref(null)
const qiraatSurahSearch = ref('')
const loadingQiraat = ref(false)

function isExcludedReciter(name) {
  const n = normalizeAr(name)
  return EXCLUDED_RECITER_KEYWORDS.some((kw) => n.includes(normalizeAr(kw)))
}

async function loadRiwayatAudio() {
  loadingQiraat.value = true
  try {
    const res = await fetch('https://mp3quran.net/api/v3/riwayat?language=ar')
    const json = await res.json()
    riwayatAudio.value = json.riwayat || []
    await loadQiraatReciters(selectedRewayaId.value)
  } catch {
    riwayatAudio.value = []
  } finally {
    loadingQiraat.value = false
  }
}

async function loadQiraatReciters(rewayaId) {
  selectedRewayaId.value = rewayaId
  loadingQiraat.value = true
  try {
    const res = await fetch(`https://mp3quran.net/api/v3/reciters?language=ar&rewaya=${rewayaId}`)
    const json = await res.json()
    const list = (json.reciters || [])
      .filter((r) => !isExcludedReciter(r.name))
      .map((r) => {
        const moshaf = (r.moshaf || []).find((m) => Number(m.moshaf_type) === Number(rewayaId)) || r.moshaf?.[0]
        return moshaf
          ? {
              id: r.id,
              name: r.name,
              server: moshaf.server,
              surahList: String(moshaf.surah_list || '').split(',').map(Number).filter(Boolean)
            }
          : null
      })
      .filter(Boolean)
    qiraatReciters.value = list
    selectedQiraatReciter.value = list[0] || null
  } catch {
    qiraatReciters.value = []
    selectedQiraatReciter.value = null
  } finally {
    loadingQiraat.value = false
  }
}

const qiraatSurahs = computed(() => {
  const rec = selectedQiraatReciter.value
  if (!rec) return []
  const q = normalizeAr(qiraatSurahSearch.value)
  return rec.surahList
    .map((num) => ({
      number: num,
      name: surahByNum(num)?.name || `سورة ${toArDigits(num)}`,
      url: `${rec.server}${String(num).padStart(3, '0')}.mp3`
    }))
    .filter((s) => !q || normalizeAr(s.name).includes(q) || String(s.number) === q)
})

function playQiraatSurah(s) {
  const rec = selectedQiraatReciter.value
  const rew = riwayatAudio.value.find((r) => r.id === selectedRewayaId.value)?.name || ''
  globalPlayer.play({
    id: `qiraat-${rec.id}-${s.number}`,
    title: `${s.name} — ${rew}`,
    subtitle: rec.name,
    series: { title: `${rec.name} (${rew})` },
    src: s.url,
    duration: 0,
    isLive: false
  })
}

/* ==================== 5. مركز التحميل (من آية إلى آية + أجزاء) ==================== */
const dlReciter = ref(qs.prefs.reciterId || 'ar.husary')
const dlJuz = ref(30)
const dlProgress = ref('')

const rangeSurah = ref(2)
const rangeFrom = ref(255)
const rangeTo = ref(257)
const rangeBusy = ref(false)
const rangeMsg = ref('')

const rangeSurahObj = computed(() => surahByNum(rangeSurah.value) || { number: 2, name: 'سورة البقرة', numberOfAyahs: 286, globalStart: 8 })
watch(rangeSurah, () => {
  const max = rangeSurahObj.value.numberOfAyahs || 1
  rangeFrom.value = 1
  rangeTo.value = Math.min(10, max)
  rangeMsg.value = ''
})

const rangeCount = computed(() => Math.max(0, (Number(rangeTo.value) || 0) - (Number(rangeFrom.value) || 0) + 1))

function downloadAyahRangeMp3() {
  const s = rangeSurahObj.value
  const max = s.numberOfAyahs || 1
  const from = Math.max(1, Math.min(max, Number(rangeFrom.value) || 1))
  const to = Math.max(from, Math.min(max, Number(rangeTo.value) || from))
  if (to - from + 1 > 60) {
    rangeMsg.value = 'الحد الأقصى للدمج المباشر ٦٠ آية في المرة الواحدة (للسورة الكاملة استخدم التحميل بالأسفل).'
    return
  }
  const gFrom = (s.globalStart || 1) + (from - 1)
  const gTo = (s.globalStart || 1) + (to - 1)
  const filename = `surah-${s.number}-ayah-${from}-to-${to}.mp3`
  const dlUrl = `${API_BASE}/quran/download-range?reciter=${encodeURIComponent(dlReciter.value)}&from=${gFrom}&to=${gTo}&name=${encodeURIComponent(filename)}`
  triggerDownload(dlUrl, filename)
  rangeMsg.value = `بدأ تحميل الملف المدمج (${toArDigits(to - from + 1)} آية): ${filename}`
}

function downloadAyahRangeText() {
  const s = rangeSurahObj.value
  const max = s.numberOfAyahs || 1
  const from = Math.max(1, Math.min(max, Number(rangeFrom.value) || 1))
  const to = Math.max(from, Math.min(max, Number(rangeTo.value) || from))
  const rMap = activeRiwayahMap.value
  const rewName = READING_RIWAYAT.find((r) => r.id === qs.prefs.riwayahId)?.name || 'حفص عن عاصم'
  const lines = [`${s.name} (${rewName}) — من الآية ${from} إلى الآية ${to}`, '='.repeat(40)]
  for (let i = from; i <= to; i++) {
    lines.push(`(${i}) ${rMap.get(`${s.number}:${i}`) || ''}`)
  }
  const blob = new Blob([lines.join('\n\n')], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  triggerDownload(url, `surah-${s.number}-ayah-${from}-${to}.txt`)
  setTimeout(() => URL.revokeObjectURL(url), 3000)
  rangeMsg.value = `تم تنزيل نص ${toArDigits(to - from + 1)} آية بنجاح.`
}

const dlSurahNumbers = computed(() => {
  const j = QURAN_JUZS.find((x) => x.juz === dlJuz.value)
  return j ? j.surahs : [1]
})

function triggerDownload(url, filename) {
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.target = '_blank'
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
}

function downloadJuzText(juzNum) {
  const rMap = activeRiwayahMap.value
  const rewName = READING_RIWAYAT.find((r) => r.id === qs.prefs.riwayahId)?.name || ''
  const lines = [`القرآن الكريم — الجزء ${juzNum} (${rewName})`, '='.repeat(40)]
  let lastS = null
  for (let p = 1; p <= 604; p++) {
    for (const m of pagesMeta.value[p] || []) {
      if (m.juz !== juzNum) continue
      if (m.surahNumber !== lastS) {
        lastS = m.surahNumber
        lines.push(`\n--- ${m.surahName} ---`)
      }
      lines.push(`(${m.numberInSurah}) ${rMap.get(m.key) || ''}`)
    }
  }
  const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  triggerDownload(url, `quran-juz-${juzNum}.txt`)
  setTimeout(() => URL.revokeObjectURL(url), 3000)
  dlProgress.value = `تم تنزيل نص الجزء ${toArDigits(juzNum)} بنجاح.`
}

function playFullSurahInGlobalPlayer(surahNum) {
  const s = surahByNum(surahNum)
  const rec = QURAN_RECITERS.find((r) => r.id === dlReciter.value) || QURAN_RECITERS[0]
  globalPlayer.play({
    id: `quran-${rec.id}-${surahNum}`,
    title: `${s?.name || `سورة ${surahNum}`}`,
    subtitle: rec.name,
    series: { title: rec.name },
    src: surahMp3Url(rec.id, surahNum),
    duration: 0,
    isLive: false
  })
}

/* ==================== 6. البحث الفوري في كامل القرآن من الذاكرة (< 10ms) ==================== */
const quranQuery = ref('')
const searchResults = computed(() => {
  const q = normalizeAr(quranQuery.value)
  if (q.length < 2) return []
  const rMap = activeRiwayahMap.value
  const out = []
  for (let p = 1; p <= 604 && out.length < 60; p++) {
    for (const m of pagesMeta.value[p] || []) {
      const txt = rMap.get(m.key) || ''
      if (normalizeAr(txt).includes(q)) {
        out.push({ ...m, page: p, text: txt })
        if (out.length >= 60) break
      }
    }
  }
  return out
})

function onKey(e) {
  if (activeSection.value !== 'read') return
  const tag = e.target.tagName
  if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return
  if (e.key === 'ArrowLeft' && currentPage.value < 604) goToPage(currentPage.value + 1)
  if (e.key === 'ArrowRight' && currentPage.value > 1) goToPage(currentPage.value - 1)
}

onMounted(async () => {
  await initMushaf()
  if (qs.prefs.showTafsir) loadPageTafsirIfNeeded(currentPage.value)
  if (activeSection.value === 'qiraat') loadRiwayatAudio()
  window.addEventListener('keydown', onKey)
})

onBeforeUnmount(() => {
  stopRecitation()
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div class="page quran-page">
    <header class="page-head q-head">
      <div>
        <h1>المصحف الشريف والقراءات</h1>
        <p>قراءة فورية صفحة بصفحة (٦٠٤ صفحة) بـ ٨ روايات معتمدة بدون أي تحميل أثناء التقليب، مع صوتيات ٢٠ رواية وتحميل من آية إلى آية.</p>
      </div>
      <button
        v-if="qs.lastRead && activeSection !== 'read'"
        type="button"
        class="btn btn-primary"
        @click="goToPage(qs.lastRead.page || 1)"
      >
        <i class="ti ti-bookmark" aria-hidden="true"></i>
        متابعة: صفحة {{ toArDigits(qs.lastRead.page || 1) }}
      </button>
    </header>

    <nav class="q-tabs" role="tablist" aria-label="أقسام المصحف">
      <button
        v-for="sec in SECTIONS"
        :key="sec.id"
        type="button"
        role="tab"
        :aria-selected="activeSection === sec.id"
        class="q-tab"
        :class="{ active: activeSection === sec.id }"
        @click="setSection(sec.id)"
      >
        <i class="ti" :class="sec.icon" aria-hidden="true"></i>
        <span>{{ sec.label }}</span>
      </button>
    </nav>

    <!-- ==================== 1. مصحف القراءة والقراءات (صفحة بصفحة — 0ms) ==================== -->
    <section v-if="activeSection === 'read'" class="reader-wrap">
      <!-- شريط اختيار الرواية للقراءة بضغطة واحدة -->
      <div class="riwayah-bar" role="group" aria-label="اختر الرواية للقراءة">
        <span class="rb-lbl"><i class="ti ti-book"></i> الرواية:</span>
        <button
          v-for="rw in READING_RIWAYAT"
          :key="rw.id"
          type="button"
          class="rw-pill"
          :class="{ on: qs.prefs.riwayahId === rw.id }"
          @click="qs.prefs.riwayahId = rw.id"
        >
          {{ rw.short }}
        </button>
        <span v-if="riwayahSwitching" class="rw-loading">جاري التبديل...</span>
      </div>

      <div class="ctrl-card">
        <div class="ctrl-row">
          <form class="page-jump" @submit.prevent="goToPage(pageInput)">
            <button type="button" class="mini-btn" :disabled="currentPage <= 1" aria-label="الصفحة السابقة" @click="goToPage(currentPage - 1)">
              <i class="ti ti-chevron-right"></i>
            </button>
            <label class="pj-lbl">
              صفحة
              <input v-model.number="pageInput" type="number" min="1" max="604" class="pj-in" />
              <span>/ ٦٠٤</span>
            </label>
            <button type="button" class="mini-btn" :disabled="currentPage >= 604" aria-label="الصفحة التالية" @click="goToPage(currentPage + 1)">
              <i class="ti ti-chevron-left"></i>
            </button>
          </form>

          <select class="q-select" aria-label="انتقال إلى سورة" @change="$event.target.value && openSurahPage(Number($event.target.value))">
            <option value="">انتقال إلى سورة...</option>
            <option v-for="s in surahs" :key="s.number" :value="s.number">
              {{ toArDigits(s.number) }}. {{ s.name }}
            </option>
          </select>

          <select class="q-select sm" aria-label="انتقال إلى جزء" @change="$event.target.value && openJuzPage(Number($event.target.value))">
            <option value="">الجزء ({{ toArDigits(pageJuz) }})</option>
            <option v-for="j in QURAN_JUZS" :key="j.juz" :value="j.juz">{{ toArDigits(j.juz) }}. {{ j.name }}</option>
          </select>

          <select v-model="qs.prefs.reciterId" class="q-select" aria-label="اختر القارئ">
            <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>

        <div class="ctrl-row bar2">
          <div class="seg" role="group" aria-label="وضع العرض">
            <button :class="{ on: qs.prefs.viewMode === 'mushaf' }" @click="qs.prefs.viewMode = 'mushaf'">صفحة المصحف</button>
            <button :class="{ on: qs.prefs.viewMode === 'verses' }" @click="qs.prefs.viewMode = 'verses'">آية بآية</button>
          </div>

          <div class="seg" role="group" aria-label="سمة القراءة">
            <button :class="{ on: qs.prefs.theme === 'light' }" @click="qs.prefs.theme = 'light'">أبيض</button>
            <button :class="{ on: qs.prefs.theme === 'paper' }" @click="qs.prefs.theme = 'paper'">ورقي</button>
            <button :class="{ on: qs.prefs.theme === 'dark' }" @click="qs.prefs.theme = 'dark'">ليلي</button>
          </div>

          <div class="font-ctl">
            <button type="button" class="mini-btn" aria-label="تصغير الخط" @click="qs.prefs.fontSize = Math.max(20, qs.prefs.fontSize - 2)">أ-</button>
            <span>{{ toArDigits(qs.prefs.fontSize) }}</span>
            <button type="button" class="mini-btn" aria-label="تكبير الخط" @click="qs.prefs.fontSize = Math.min(42, qs.prefs.fontSize + 2)">أ+</button>
          </div>

          <select v-model="qs.prefs.tafsirId" class="q-select sm" aria-label="اختر التفسير">
            <option v-for="t in QURAN_TAFSIRS" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>

          <button type="button" class="pill-btn" :class="{ on: qs.prefs.showTafsir }" @click="qs.prefs.showTafsir = !qs.prefs.showTafsir">
            <i class="ti ti-book"></i> التفسير
          </button>
        </div>
      </div>

      <div class="recite-bar">
        <button type="button" class="play-btn" @click="toggleRecitation">
          <i class="ti" :class="isReciting ? 'ti-player-pause' : 'ti-player-play'" aria-hidden="true"></i>
          {{ isReciting ? 'إيقاف التلاوة' : 'تلاوة الصفحة' }}
        </button>

        <span v-if="playingAyahIdx >= 0" class="recite-status">
          {{ pageAyahs[playingAyahIdx]?.surahName }} — آية {{ toArDigits(pageAyahs[playingAyahIdx]?.numberInSurah) }}
        </span>
        <span v-else class="recite-status muted">اسحب يمينًا أو يسارًا لتقليب الصفحات فورًا · اضغط على أي آية للتفسير والتلاوة</span>

        <div class="recite-extra">
          <button type="button" class="mini-pill" title="تكرار الآية للحفظ" @click="cycleRepeat">
            <i class="ti ti-repeat"></i> {{ qs.prefs.repeatCount === 0 ? '∞' : `${toArDigits(qs.prefs.repeatCount)}×` }}
          </button>
          <button type="button" class="mini-pill" title="سرعة التلاوة" @click="cycleRate">
            {{ toArDigits(qs.prefs.rate) }}×
          </button>
          <button v-if="playingAyahIdx >= 0" type="button" class="mini-pill" aria-label="إيقاف" @click="stopRecitation">
            <i class="ti ti-player-stop"></i>
          </button>
        </div>
      </div>

      <div v-if="initLoading" class="empty">جاري تجهيز المصحف كاملًا للمرة الأولى (بعدها التنقل بين كل ٦٠٤ صفحة فوري بدون تحميل)...</div>
      <div v-else-if="initError" class="empty">
        <p>{{ initError }}</p>
        <button type="button" class="btn btn-primary" style="margin-top:10px" @click="initMushaf">إعادة المحاولة</button>
      </div>

      <!-- ورقة المصحف الفورية -->
      <article
        v-else
        class="mushaf-sheet"
        :class="`theme-${qs.prefs.theme}`"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
      >
        <div class="page-top-strip">
          <span>{{ pageSurahs.join(' · ') }}</span>
          <strong>
            صفحة {{ toArDigits(currentPage) }} · {{ READING_RIWAYAT.find(r => r.id === qs.prefs.riwayahId)?.name }}
          </strong>
          <span>الجزء {{ toArDigits(pageJuz) }}</span>
        </div>

        <template v-if="qs.prefs.viewMode === 'mushaf'">
          <div v-for="block in pageBlocks" :key="`${block.surahNumber}-${block.items[0]?.numberInSurah}`" class="mushaf-block">
            <div v-if="block.startsAtOne" class="surah-banner">
              <span class="s-meta">{{ block.revelationType === 'Meccan' ? 'مكية' : 'مدنية' }}</span>
              <h2>{{ block.surahName }}</h2>
              <span class="s-meta">سورة {{ toArDigits(block.surahNumber) }}</span>
            </div>
            <p v-if="block.startsAtOne && block.surahNumber !== 1 && block.surahNumber !== 9" class="basmala">
              بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ
            </p>

            <div class="mushaf-flow" :style="{ fontSize: qs.prefs.fontSize + 'px' }">
              <span
                v-for="a in block.items"
                :id="`ayah-${a.key}`"
                :key="a.key"
                class="ayah-inline"
                :class="{
                  active: playingAyahIdx === a.pageIdx,
                  selected: selectedAyahKey === a.key,
                  marked: qs.isBookmarked(a.surahNumber, a.numberInSurah)
                }"
                @click="selectedAyahKey = selectedAyahKey === a.key ? null : a.key"
              >
                {{ a.text }}
                <span class="ayah-end">﴿{{ toArDigits(a.numberInSurah) }}﴾</span>
              </span>
            </div>
          </div>

          <div v-if="selectedAyahObj" class="ayah-popover">
            <div class="ap-head">
              <strong>{{ selectedAyahObj.surahName }} — آية {{ toArDigits(selectedAyahObj.numberInSurah) }}</strong>
              <button type="button" class="mini-btn" @click="selectedAyahKey = null"><i class="ti ti-x"></i></button>
            </div>
            <div class="ap-actions">
              <button type="button" class="pill-btn on" @click="playAyahAt(pageAyahs.findIndex(x => x.key === selectedAyahObj.key))">
                <i class="ti ti-player-play"></i> تلاوة من هنا
              </button>
              <button
                type="button"
                class="pill-btn"
                :class="{ on: qs.isBookmarked(selectedAyahObj.surahNumber, selectedAyahObj.numberInSurah) }"
                @click="qs.toggleBookmark({ surah: selectedAyahObj.surahNumber, surahName: selectedAyahObj.surahName, ayah: selectedAyahObj.numberInSurah, text: selectedAyahObj.text })"
              >
                <i class="ti ti-bookmark"></i> {{ qs.isBookmarked(selectedAyahObj.surahNumber, selectedAyahObj.numberInSurah) ? 'محفوظة' : 'حفظ علامة' }}
              </button>
              <button type="button" class="pill-btn" @click="copyAyah(selectedAyahObj)">
                <i class="ti" :class="copiedAyah === selectedAyahObj.key ? 'ti-check' : 'ti-copy'"></i>
                {{ copiedAyah === selectedAyahObj.key ? 'تم النسخ' : 'نسخ' }}
              </button>
              <button type="button" class="pill-btn" @click="shareAyah(selectedAyahObj)">
                <i class="ti ti-share"></i> مشاركة
              </button>
            </div>
            <div v-if="selectedAyahObj.tafsir" class="ap-tafsir">
              <small>{{ QURAN_TAFSIRS.find(t => t.id === qs.prefs.tafsirId)?.name }}:</small>
              <p>{{ selectedAyahObj.tafsir }}</p>
            </div>
          </div>
        </template>

        <div v-else class="verses-list">
          <div
            v-for="(a, idx) in pageAyahs"
            :id="`ayah-${a.key}`"
            :key="a.key"
            class="verse-card"
            :class="{ active: playingAyahIdx === idx }"
          >
            <div class="vc-top">
              <span class="vc-num">{{ a.surahName }} · آية {{ toArDigits(a.numberInSurah) }}</span>
              <div class="vc-acts">
                <button type="button" class="vc-btn" title="تلاوة" @click="playAyahAt(idx)">
                  <i class="ti" :class="playingAyahIdx === idx && isReciting ? 'ti-player-pause' : 'ti-player-play'"></i>
                </button>
                <button
                  type="button"
                  class="vc-btn"
                  :class="{ on: qs.isBookmarked(a.surahNumber, a.numberInSurah) }"
                  title="حفظ علامة"
                  @click="qs.toggleBookmark({ surah: a.surahNumber, surahName: a.surahName, ayah: a.numberInSurah, text: a.text })"
                >
                  <i class="ti" :class="qs.isBookmarked(a.surahNumber, a.numberInSurah) ? 'ti-bookmark-filled' : 'ti-bookmark'"></i>
                </button>
                <button type="button" class="vc-btn" title="نسخ" @click="copyAyah(a)">
                  <i class="ti" :class="copiedAyah === a.key ? 'ti-check' : 'ti-copy'"></i>
                </button>
                <button type="button" class="vc-btn" title="مشاركة" @click="shareAyah(a)">
                  <i class="ti ti-share"></i>
                </button>
              </div>
            </div>
            <p class="vc-text" :style="{ fontSize: qs.prefs.fontSize + 'px' }">{{ a.text }} <span class="ayah-end">﴿{{ toArDigits(a.numberInSurah) }}﴾</span></p>
            <p v-if="qs.prefs.showTafsir && a.tafsir" class="vc-tafsir">{{ a.tafsir }}</p>
          </div>
        </div>

        <div class="surah-nav">
          <button v-if="currentPage > 1" type="button" class="btn btn-soft" @click="goToPage(currentPage - 1)">
            <i class="ti ti-chevron-right"></i> السابقة ({{ toArDigits(currentPage - 1) }})
          </button>
          <span v-else></span>
          <span class="page-num-foot">صفحة {{ toArDigits(currentPage) }} من ٦٠٤</span>
          <button v-if="currentPage < 604" type="button" class="btn btn-primary" @click="goToPage(currentPage + 1)">
            التالية ({{ toArDigits(currentPage + 1) }}) <i class="ti ti-chevron-left"></i>
          </button>
        </div>
      </article>
    </section>

    <!-- ==================== 2. صوتيات القراءات والروايات (20 رواية) ==================== -->
    <section v-else-if="activeSection === 'qiraat'" class="panel">
      <div class="ctrl-card">
        <div class="ctrl-row">
          <label class="dl-lbl">
            اختر الرواية / القراءة (٢٠ رواية معتمدة):
            <select :value="selectedRewayaId" class="q-select" @change="loadQiraatReciters(Number($event.target.value))">
              <option v-for="rw in riwayatAudio" :key="rw.id" :value="rw.id">{{ rw.name }}</option>
            </select>
          </label>
          <label class="dl-lbl">
            اختر القارئ بهذه الرواية ({{ toArDigits(qiraatReciters.length) }} قارئ):
            <select
              :value="selectedQiraatReciter?.id || ''"
              class="q-select"
              @change="selectedQiraatReciter = qiraatReciters.find(r => r.id === Number($event.target.value))"
            >
              <option v-for="r in qiraatReciters" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </label>
        </div>
        <div class="search-box">
          <i class="ti ti-search"></i>
          <input v-model="qiraatSurahSearch" type="search" placeholder="ابحث باسم السورة للاستماع أو التحميل بهذه الرواية..." />
        </div>
      </div>

      <div v-if="loadingQiraat" class="empty">جاري تحميل القراء والروايات...</div>
      <div v-else-if="!qiraatSurahs.length" class="empty">لا توجد سور متاحة لهذا الاختيار.</div>
      <div v-else class="dl-list">
        <div v-for="s in qiraatSurahs" :key="s.number" class="dl-row">
          <span class="sc-num">{{ toArDigits(s.number) }}</span>
          <div class="dl-info">
            <strong>{{ s.name }}</strong>
            <small>{{ selectedQiraatReciter?.name }} · {{ riwayatAudio.find(r => r.id === selectedRewayaId)?.name }}</small>
          </div>
          <div class="dl-acts">
            <button type="button" class="pill-btn" @click="playQiraatSurah(s)">
              <i class="ti ti-player-play"></i> استماع
            </button>
            <a :href="s.url" target="_blank" rel="noopener" download class="pill-btn on">
              <i class="ti ti-download"></i> تحميل MP3
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== 3. مركز التحميل (من آية إلى آية + أجزاء وسور) ==================== -->
    <section v-else-if="activeSection === 'downloads'" class="panel">
      <div class="ctrl-card range-card">
        <h3 style="margin:0;font-size:17px"><i class="ti ti-scissors"></i> تحميل نطاق آيات محدد (من آية إلى آية)</h3>
        <p style="margin:0;font-size:13.5px;color:var(--text-2)">حدّد السورة والقارئ وبداية ونهاية الآيات، وسيتم دمجها وتنزيلها في ملف MP3 واحد مباشرة.</p>

        <div class="ctrl-row">
          <label class="dl-lbl">
            القارئ:
            <select v-model="dlReciter" class="q-select">
              <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </label>
          <label class="dl-lbl">
            السورة:
            <select v-model.number="rangeSurah" class="q-select">
              <option v-for="s in surahs" :key="s.number" :value="s.number">
                {{ toArDigits(s.number) }}. {{ s.name }} ({{ toArDigits(s.numberOfAyahs) }} آية)
              </option>
            </select>
          </label>
        </div>

        <div class="ctrl-row">
          <label class="dl-lbl sm">
            من الآية:
            <input v-model.number="rangeFrom" type="number" min="1" :max="rangeSurahObj.numberOfAyahs" class="q-select" />
          </label>
          <label class="dl-lbl sm">
            إلى الآية:
            <input v-model.number="rangeTo" type="number" :min="rangeFrom" :max="rangeSurahObj.numberOfAyahs" class="q-select" />
          </label>
        </div>

        <div class="ctrl-row">
          <button type="button" class="btn btn-primary" :disabled="rangeBusy || rangeCount <= 0" @click="downloadAyahRangeMp3">
            <i class="ti ti-download"></i>
            تحميل MP3 مدمج ({{ toArDigits(rangeCount) }} آية)
          </button>
          <button type="button" class="btn btn-soft" :disabled="rangeBusy || rangeCount <= 0" @click="downloadAyahRangeText">
            <i class="ti ti-file-text"></i> تحميل النص (TXT)
          </button>
          <span v-if="rangeMsg" class="recite-status">{{ rangeMsg }}</span>
        </div>
      </div>

      <div class="ctrl-card">
        <h3 style="margin:0;font-size:17px"><i class="ti ti-books"></i> تحميل أجزاء وسور كاملة</h3>
        <div class="ctrl-row">
          <label class="dl-lbl">
            اختر الجزء:
            <select v-model.number="dlJuz" class="q-select">
              <option v-for="j in QURAN_JUZS" :key="j.juz" :value="j.juz">
                {{ toArDigits(j.juz) }}. {{ j.name }} ({{ toArDigits(j.surahs.length) }} سورة)
              </option>
            </select>
          </label>
          <button type="button" class="btn btn-soft" style="align-self:flex-end" @click="downloadJuzText(dlJuz)">
            <i class="ti ti-file-text"></i> تنزيل نص الجزء {{ toArDigits(dlJuz) }} (TXT)
          </button>
        </div>
        <span v-if="dlProgress" class="recite-status">{{ dlProgress }}</span>
      </div>

      <div class="dl-list">
        <div v-for="sNum in dlSurahNumbers" :key="sNum" class="dl-row">
          <span class="sc-num">{{ toArDigits(sNum) }}</span>
          <div class="dl-info">
            <strong>{{ surahByNum(sNum)?.name || `سورة ${sNum}` }}</strong>
            <small>{{ QURAN_RECITERS.find(r => r.id === dlReciter)?.name }}</small>
          </div>
          <div class="dl-acts">
            <button type="button" class="pill-btn" @click="playFullSurahInGlobalPlayer(sNum)">
              <i class="ti ti-player-play"></i> استماع
            </button>
            <a :href="surahMp3Url(dlReciter, sNum)" target="_blank" rel="noopener" download class="pill-btn on">
              <i class="ti ti-download"></i> تحميل MP3
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== 4. فهرس السور (114) ==================== -->
    <section v-else-if="activeSection === 'surahs'" class="panel">
      <div class="ctrl-row">
        <div class="search-box">
          <i class="ti ti-search"></i>
          <input v-model="surahSearch" type="search" placeholder="ابحث باسم السورة أو رقمها..." />
        </div>
        <div class="seg">
          <button :class="{ on: revFilter === 'all' }" @click="revFilter = 'all'">الكل</button>
          <button :class="{ on: revFilter === 'Meccan' }" @click="revFilter = 'Meccan'">مكية</button>
          <button :class="{ on: revFilter === 'Medinan' }" @click="revFilter = 'Medinan'">مدنية</button>
        </div>
      </div>

      <div class="surah-grid">
        <div v-for="s in filteredSurahs" :key="s.number" class="surah-card">
          <button type="button" class="sc-main" @click="openSurahPage(s.number)">
            <span class="sc-num">{{ toArDigits(s.number) }}</span>
            <span class="sc-info">
              <strong>{{ s.name }}</strong>
              <small>{{ s.revelationType === 'Meccan' ? 'مكية' : 'مدنية' }} · {{ toArDigits(s.numberOfAyahs) }} آية · ص {{ toArDigits(s.startPage) }}</small>
            </span>
          </button>
          <a :href="surahMp3Url(qs.prefs.reciterId, s.number)" target="_blank" rel="noopener" download class="sc-dl" title="تحميل MP3">
            <i class="ti ti-download"></i>
          </a>
        </div>
      </div>
    </section>

    <!-- ==================== 5. الأجزاء (30) ==================== -->
    <section v-else-if="activeSection === 'juzs'" class="panel">
      <div class="juz-grid">
        <div v-for="j in QURAN_JUZS" :key="j.juz" class="juz-card">
          <div class="jc-head">
            <span class="sc-num">{{ toArDigits(j.juz) }}</span>
            <div>
              <strong>{{ j.name }}</strong>
              <small>يبدأ من صفحة {{ toArDigits(JUZ_START_PAGES[j.juz - 1]) }} · {{ surahByNum(j.startSurah)?.name || '' }}</small>
            </div>
          </div>
          <div class="jc-acts">
            <button type="button" class="pill-btn on" @click="openJuzPage(j.juz)">
              <i class="ti ti-book-2"></i> قراءة الجزء
            </button>
            <button type="button" class="pill-btn" @click="dlJuz = j.juz; setSection('downloads')">
              <i class="ti ti-download"></i> تحميل الصوتيات
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== 6. البحث الفوري والعلامات ==================== -->
    <section v-else-if="activeSection === 'search'" class="panel">
      <div class="ctrl-row">
        <div class="search-box">
          <i class="ti ti-search"></i>
          <input v-model="quranQuery" type="search" placeholder="ابحث فوريًا في كل آيات القرآن الكريم (مثل: الصبر، الوالدين، الرحمة)..." />
        </div>
      </div>

      <p v-if="quranQuery.trim().length >= 2 && !searchResults.length" class="empty">لا توجد آيات مطابقة لهذا البحث.</p>
      <div v-else-if="searchResults.length" class="results-list">
        <button
          v-for="m in searchResults"
          :key="m.key"
          type="button"
          class="res-card"
          @click="goToPage(m.page, m.key)"
        >
          <p class="res-text">{{ m.text }}</p>
          <small>{{ m.surahName }} — الآية {{ toArDigits(m.numberInSurah) }} (صفحة {{ toArDigits(m.page) }})</small>
        </button>
      </div>

      <div class="bm-section">
        <h3>العلامات المرجعية المحفوظة ({{ toArDigits(qs.bookmarks.length) }})</h3>
        <div v-if="!qs.bookmarks.length" class="empty">لم تحفظ أي علامات بعد. اضغط على أي آية أثناء القراءة لحفظها هنا.</div>
        <div v-else class="results-list">
          <div v-for="b in qs.bookmarks" :key="b.key" class="res-card bm-row">
            <button type="button" class="bm-open" @click="openSurahPage(b.surah, b.ayah)">
              <p class="res-text">{{ b.text }}</p>
              <small>{{ b.surahName }} — الآية {{ toArDigits(b.ayah) }}</small>
            </button>
            <button type="button" class="mini-btn" aria-label="حذف العلامة" @click="qs.removeBookmark(b.key)">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.quran-page { gap: 16px; }
.q-head { flex-direction: row; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }

.q-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.q-tab {
  display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
  padding: 9px 14px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-2); font-size: 14px; font-weight: 500; transition: .15s var(--ease);
}
.q-tab.active { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

.riwayah-bar {
  display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
  padding: 10px 12px; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border);
  margin-bottom: 10px;
}
.rb-lbl { font-size: 13.5px; font-weight: 600; color: var(--primary); display: inline-flex; align-items: center; gap: 5px; margin-inline-end: 4px; }
.rw-pill {
  padding: 5px 12px; border-radius: 999px; font-size: 13.5px; font-weight: 500;
  background: var(--bg); border: 1px solid var(--border); color: var(--text-2);
  transition: .12s var(--ease); flex-shrink: 0;
}
.rw-pill:hover { border-color: var(--primary); color: var(--primary); }
.rw-pill.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
.rw-loading { font-size: 12.5px; color: var(--text-3); margin-inline-start: auto; }

.ctrl-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px; display: flex; flex-direction: column; gap: 10px;
}
.range-card { border-color: var(--primary); background: color-mix(in srgb, var(--primary-soft) 55%, var(--surface)); }
.ctrl-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.q-select {
  padding: 8px 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong); background: var(--bg); color: var(--text);
  font-family: inherit; font-size: 14px; flex: 1; min-width: 150px;
}
.q-select.sm { flex: 0 1 150px; min-width: 120px; }

.page-jump {
  display: inline-flex; align-items: center; gap: 4px;
  background: var(--bg); border: 1px solid var(--border-strong);
  border-radius: 999px; padding: 2px 6px;
}
.pj-lbl { display: inline-flex; align-items: center; gap: 5px; font-size: 13.5px; color: var(--text-2); }
.pj-in { width: 56px; text-align: center; border: 0; background: var(--surface); border-radius: 8px; padding: 4px; font-weight: 700; font-size: 14px; }

.seg { display: inline-flex; background: var(--bg); border: 1px solid var(--border); padding: 3px; border-radius: 999px; gap: 2px; }
.seg button { height: 30px; padding: 0 11px; border-radius: 999px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--primary); color: var(--on-primary); font-weight: 500; }

.font-ctl { display: inline-flex; align-items: center; gap: 6px; background: var(--bg); border: 1px solid var(--border); padding: 2px 8px; border-radius: 999px; font-size: 13px; }
.mini-btn { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; color: var(--text-2); }
.mini-btn:hover:not(:disabled) { background: var(--surface-2); color: var(--text); }
.mini-btn:disabled { opacity: .35; cursor: not-allowed; }
.pill-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 34px; padding: 0 12px; border-radius: 999px;
  background: var(--bg); border: 1px solid var(--border);
  color: var(--text-2); font-size: 13px; font-weight: 500; transition: .15s var(--ease);
}
.pill-btn:hover { border-color: var(--primary); color: var(--primary); }
.pill-btn.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

.recite-bar {
  position: sticky; top: 68px; z-index: 8; margin: 10px 0;
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
  padding: 10px 14px; border-radius: var(--radius);
  background: color-mix(in srgb, var(--surface) 92%, transparent);
  backdrop-filter: blur(10px); border: 1px solid var(--border);
}
.play-btn {
  display: inline-flex; align-items: center; gap: 7px;
  height: 38px; padding: 0 16px; border-radius: 999px;
  background: var(--primary); color: var(--on-primary); font-size: 14px; font-weight: 500;
}
.recite-status { font-size: 13.5px; font-weight: 500; color: var(--primary); }
.recite-status.muted { color: var(--text-3); font-weight: 400; }
.recite-extra { display: flex; align-items: center; gap: 6px; }
.mini-pill {
  display: inline-flex; align-items: center; gap: 4px;
  height: 32px; padding: 0 10px; border-radius: 999px;
  background: var(--bg); border: 1px solid var(--border); font-size: 13px; color: var(--text-2);
}

.mushaf-sheet {
  border-radius: 18px; padding: 24px 26px; border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 18px;
  max-width: 820px; width: 100%; margin: 0 auto;
  box-shadow: 0 6px 26px rgba(0, 0, 0, 0.05);
}
.mushaf-sheet.theme-light { background: #ffffff; color: #1b2420; }
.mushaf-sheet.theme-paper { background: #fbf6ea; color: #2b2113; border-color: #e9dec5; }
.mushaf-sheet.theme-dark { background: #141c19; color: #e8eee9; border-color: #273530; }

.page-top-strip {
  display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
  font-size: 13.5px; padding-bottom: 10px; border-bottom: 1px solid rgba(128, 128, 128, 0.2);
  opacity: .85;
}
.page-top-strip strong { color: var(--primary); font-size: 15px; }
.mushaf-block { display: flex; flex-direction: column; gap: 12px; }

.surah-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 10px 18px; border-radius: 14px;
  background: rgba(47, 107, 90, 0.09); border: 1px solid rgba(47, 107, 90, 0.22);
}
.surah-banner h2 { font-family: 'Amiri Quran', var(--font-quote); font-size: 25px; margin: 0; color: var(--primary); }
.s-meta { font-size: 13px; opacity: .75; font-weight: 500; }
.basmala { text-align: center; font-family: 'Amiri Quran', var(--font-quote); font-size: 28px; margin: 0; color: var(--primary); }

.mushaf-flow {
  font-family: 'Amiri Quran', var(--font-quote);
  line-height: 2.35;
  text-align: justify;
  text-align-last: center;
}
.ayah-inline { cursor: pointer; border-radius: 8px; padding: 2px 3px; transition: background .15s ease; }
.ayah-inline:hover { background: rgba(47, 107, 90, 0.1); }
.ayah-inline.active { background: rgba(47, 107, 90, 0.22); }
.ayah-inline.selected { background: rgba(217, 155, 38, 0.22); }
.ayah-inline.marked .ayah-end { color: #d97706; }
.ayah-end { color: var(--primary); font-size: 0.78em; margin-inline: 4px; white-space: nowrap; }

.ayah-popover {
  position: sticky; bottom: 86px; z-index: 12;
  background: var(--bg); color: var(--text);
  border: 1px solid var(--primary); border-radius: 14px; padding: 14px 16px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16); display: flex; flex-direction: column; gap: 10px;
}
.ap-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 14px; }
.ap-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.ap-tafsir { background: var(--surface); padding: 10px 12px; border-radius: 10px; font-size: 14px; line-height: 1.8; color: var(--text-2); }
.ap-tafsir small { display: block; color: var(--primary); font-weight: 600; margin-bottom: 4px; }

.verses-list { display: flex; flex-direction: column; gap: 14px; }
.verse-card { padding: 16px; border-radius: 14px; border: 1px solid rgba(128, 128, 128, 0.2); display: flex; flex-direction: column; gap: 10px; }
.verse-card.active { border-color: var(--primary); background: rgba(47, 107, 90, 0.08); }
.vc-top { display: flex; align-items: center; justify-content: space-between; }
.vc-num { font-size: 12.5px; opacity: .7; font-weight: 600; }
.vc-acts { display: flex; gap: 4px; }
.vc-btn { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-size: 17px; opacity: .8; }
.vc-btn:hover, .vc-btn.on { background: var(--primary); color: var(--on-primary); opacity: 1; }
.vc-text { font-family: 'Amiri Quran', var(--font-quote); line-height: 2.2; margin: 0; }
.vc-tafsir { margin: 0; padding-top: 10px; border-top: 1px dashed rgba(128, 128, 128, 0.25); font-size: 15px; line-height: 1.85; opacity: .85; }

.surah-nav { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-top: 12px; border-top: 1px solid rgba(128, 128, 128, 0.2); }
.page-num-foot { font-size: 13.5px; opacity: .7; font-weight: 600; }

.panel { display: flex; flex-direction: column; gap: 14px; }
.search-box {
  flex: 1; min-width: 220px; display: flex; align-items: center; gap: 8px;
  height: 40px; padding: 0 14px; border-radius: 999px;
  background: var(--bg); border: 1px solid var(--border);
}
.search-box input { flex: 1; min-width: 0; border: 0; background: none; outline: none; font-size: 14.5px; }
.surah-grid, .juz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.surah-card { display: flex; align-items: center; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border); overflow: hidden; }
.surah-card:hover { border-color: var(--primary); }
.sc-main { flex: 1; display: flex; align-items: center; gap: 12px; padding: 12px; text-align: right; }
.sc-num {
  width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-weight: 700; font-size: 14px;
}
.sc-info { display: flex; flex-direction: column; }
.sc-info strong { font-size: 15px; }
.sc-info small { font-size: 12.5px; color: var(--text-3); }
.sc-dl { width: 42px; height: 100%; display: grid; place-items: center; color: var(--text-3); border-inline-start: 1px solid var(--border); }
.sc-dl:hover { background: var(--primary); color: var(--on-primary); }

.juz-card { padding: 14px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border); display: flex; flex-direction: column; gap: 12px; }
.jc-head { display: flex; align-items: center; gap: 10px; }
.jc-head strong { display: block; font-size: 15px; }
.jc-head small { font-size: 12.5px; color: var(--text-3); }
.jc-acts { display: flex; gap: 8px; flex-wrap: wrap; }

.dl-lbl { display: flex; flex-direction: column; gap: 5px; font-size: 13px; color: var(--text-2); flex: 1; min-width: 200px; }
.dl-lbl.sm { flex: 0 1 160px; min-width: 130px; }
.dl-list { display: flex; flex-direction: column; gap: 8px; }
.dl-row {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: 12px 14px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border);
}
.dl-info { flex: 1; min-width: 140px; display: flex; flex-direction: column; }
.dl-info small { font-size: 12.5px; color: var(--text-3); }
.dl-acts { display: flex; gap: 8px; }

.results-list { display: flex; flex-direction: column; gap: 10px; }
.res-card {
  width: 100%; text-align: right; padding: 14px 16px; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 6px;
}
.res-card:hover { border-color: var(--primary); }
.res-text { font-family: 'Amiri Quran', var(--font-quote); font-size: 22px; line-height: 1.9; margin: 0; }
.res-card small { color: var(--primary); font-size: 13px; font-weight: 500; }
.bm-section { margin-top: 12px; display: flex; flex-direction: column; gap: 10px; }
.bm-row { flex-direction: row; align-items: center; justify-content: space-between; gap: 10px; }
.bm-open { flex: 1; text-align: right; }

@media (max-width: 900px) {
  .q-tabs, .riwayah-bar { flex-wrap: nowrap; overflow-x: auto; margin-inline: -16px; padding: 6px 16px; scrollbar-width: none; border-radius: 0; border-inline: 0; }
  .q-tabs::-webkit-scrollbar, .riwayah-bar::-webkit-scrollbar { display: none; }
  .mushaf-sheet { padding: 16px 14px; }
  .recite-bar { top: 60px; }
}
</style>
