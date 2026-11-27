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

// كاش دائم في الذاكرة لتقليب كل الـ ٦٠٤ صفحة والروايات في ٠ مللي ثانية
let cachedInfo = null
const riwayahCache = new Map()
const tafsirCache = new Map()

const route = useRoute()
const router = useRouter()
const qs = useQuranStore()
const globalPlayer = usePlayerStore()
const API_BASE = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '')

// الدرج النشط: null (صفحة المصحف الهادئة) | 'index' | 'settings' | 'downloads'
const drawer = ref(null)
const indexTab = ref('surahs') // 'surahs' | 'juzs' | 'search' | 'bookmarks'
const dlTab = ref('range') // 'range' | 'juz' | 'qiraat'

function openDrawer(name) {
  drawer.value = drawer.value === name ? null : name
  if (name === 'downloads' && dlTab.value === 'qiraat' && !riwayatAudio.value.length) {
    loadRiwayatAudio()
  }
}

/* ==================== 1. تحميل المصحف والرواية مرة واحدة ==================== */
const surahs = ref([])
const pagesMeta = ref({})
const activeRiwayahMap = ref(new Map())
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
  if (newId) await ensureRiwayahLoaded(newId).catch(() => {})
})

const filteredSurahs = computed(() => {
  const q = normalizeAr(surahSearch.value)
  return surahs.value.filter((s) => {
    if (revFilter.value !== 'all' && s.revelationType !== revFilter.value) return false
    if (!q) return true
    return normalizeAr(s.name).includes(q) || normalizeAr(s.englishName).includes(q) || String(s.number) === q
  })
})

function surahByNum(num) {
  return surahs.value.find((s) => s.number === num)
}

/* ==================== 2. عرض الصفحة الفوري (0ms) ==================== */
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

  return metaList.map((m) => ({
    ...m,
    text: stripLeadingBasmala(rMap.get(m.key) || '', m.surahNumber, m.numberInSurah),
    audio: `https://cdn.islamic.network/quran/audio/128/${recId}/${m.number}.mp3`,
    tafsir: tMap.get(m.key) || ''
  }))
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
  drawer.value = null
  router.replace({ query: { page: target } })

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
  if (ayahNum <= 1) return goToPage(s.startPage)
  const key = `${surahNum}:${ayahNum}`
  for (let p = s.startPage; p <= 604; p++) {
    if ((pagesMeta.value[p] || []).some((m) => m.key === key)) return goToPage(p, key)
  }
  goToPage(s.startPage)
}

function openJuzPage(juzNum) {
  goToPage(JUZ_START_PAGES[juzNum - 1] || 1)
}

watch(() => [qs.prefs.showTafsir, qs.prefs.tafsirId, selectedAyahKey.value], () => {
  if (qs.prefs.showTafsir || selectedAyahKey.value) loadPageTafsirIfNeeded(currentPage.value)
})

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
    if (dx > 0 && currentPage.value < 604) goToPage(currentPage.value + 1)
    else if (dx < 0 && currentPage.value > 1) goToPage(currentPage.value - 1)
  }
}

/* ==================== 3. التلاوة المتزامنة ==================== */
const ayahAudio = typeof Audio !== 'undefined' ? new Audio() : null
const playingAyahIdx = ref(-1)
const isReciting = ref(false)
let repeatDone = 0

if (ayahAudio) {
  ayahAudio.addEventListener('play', () => {
    isReciting.value = true
    if (globalPlayer.playing) globalPlayer.pause()
  })
  ayahAudio.addEventListener('pause', () => (isReciting.value = false))
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
    if (playingAyahIdx.value + 1 < pageAyahs.value.length) playAyahAt(playingAyahIdx.value + 1)
    else if (currentPage.value < 604) goToPage(currentPage.value + 1, null, true)
    else { isReciting.value = false; playingAyahIdx.value = -1 }
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
  if (ayahAudio) { ayahAudio.pause(); ayahAudio.currentTime = 0 }
  isReciting.value = false
  playingAyahIdx.value = -1
  repeatDone = 0
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

/* ==================== 4. صوتيات القراءات (20 رواية) + مركز التحميل ==================== */
const riwayatAudio = ref([])
const selectedRewayaId = ref(2)
const qiraatReciters = ref([])
const selectedQiraatReciter = ref(null)
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
          ? { id: r.id, name: r.name, server: moshaf.server, surahList: String(moshaf.surah_list || '').split(',').map(Number).filter(Boolean) }
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
  return rec.surahList.map((num) => ({
    number: num,
    name: surahByNum(num)?.name || `سورة ${toArDigits(num)}`,
    url: `${rec.server}${String(num).padStart(3, '0')}.mp3`
  }))
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

const dlReciter = ref(qs.prefs.reciterId || 'ar.husary')
const dlJuz = ref(30)
const rangeSurah = ref(2)
const rangeFrom = ref(255)
const rangeTo = ref(257)
const rangeMsg = ref('')

const rangeSurahObj = computed(() => surahByNum(rangeSurah.value) || { number: 2, name: 'سورة البقرة', numberOfAyahs: 286, globalStart: 8 })
watch(rangeSurah, () => {
  rangeFrom.value = 1
  rangeTo.value = Math.min(10, rangeSurahObj.value.numberOfAyahs || 1)
  rangeMsg.value = ''
})
const rangeCount = computed(() => Math.max(0, (Number(rangeTo.value) || 0) - (Number(rangeFrom.value) || 0) + 1))

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

function downloadAyahRangeMp3() {
  const s = rangeSurahObj.value
  const max = s.numberOfAyahs || 1
  const from = Math.max(1, Math.min(max, Number(rangeFrom.value) || 1))
  const to = Math.max(from, Math.min(max, Number(rangeTo.value) || from))
  if (to - from + 1 > 60) {
    rangeMsg.value = 'الحد الأقصى للدمج المباشر ٦٠ آية في المرة الواحدة.'
    return
  }
  const gFrom = (s.globalStart || 1) + (from - 1)
  const gTo = (s.globalStart || 1) + (to - 1)
  const filename = `surah-${s.number}-ayah-${from}-to-${to}.mp3`
  triggerDownload(`${API_BASE}/quran/download-range?reciter=${encodeURIComponent(dlReciter.value)}&from=${gFrom}&to=${gTo}&name=${encodeURIComponent(filename)}`, filename)
  rangeMsg.value = `بدأ تحميل ${toArDigits(to - from + 1)} آية في ملف MP3 واحد.`
}

function downloadAyahRangeText() {
  const s = rangeSurahObj.value
  const max = s.numberOfAyahs || 1
  const from = Math.max(1, Math.min(max, Number(rangeFrom.value) || 1))
  const to = Math.max(from, Math.min(max, Number(rangeTo.value) || from))
  const rMap = activeRiwayahMap.value
  const lines = [`${s.name} — من الآية ${from} إلى الآية ${to}`, '='.repeat(36)]
  for (let i = from; i <= to; i++) lines.push(`(${i}) ${rMap.get(`${s.number}:${i}`) || ''}`)
  const url = URL.createObjectURL(new Blob([lines.join('\n\n')], { type: 'text/plain;charset=utf-8' }))
  triggerDownload(url, `surah-${s.number}-ayah-${from}-${to}.txt`)
  setTimeout(() => URL.revokeObjectURL(url), 3000)
  rangeMsg.value = `تم تنزيل النص بنجاح.`
}

const dlSurahNumbers = computed(() => QURAN_JUZS.find((x) => x.juz === dlJuz.value)?.surahs || [1])

/* ==================== 5. البحث الفوري (<10ms) ==================== */
const quranQuery = ref('')
const searchResults = computed(() => {
  const q = normalizeAr(quranQuery.value)
  if (q.length < 2) return []
  const rMap = activeRiwayahMap.value
  const out = []
  for (let p = 1; p <= 604 && out.length < 50; p++) {
    for (const m of pagesMeta.value[p] || []) {
      const txt = rMap.get(m.key) || ''
      if (normalizeAr(txt).includes(q)) {
        out.push({ ...m, page: p, text: txt })
        if (out.length >= 50) break
      }
    }
  }
  return out
})

function onKey(e) {
  if (drawer.value) {
    if (e.key === 'Escape') drawer.value = null
    return
  }
  const tag = e.target.tagName
  if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return
  if (e.key === 'ArrowLeft' && currentPage.value < 604) goToPage(currentPage.value + 1)
  if (e.key === 'ArrowRight' && currentPage.value > 1) goToPage(currentPage.value - 1)
}

onMounted(async () => {
  await initMushaf()
  if (qs.prefs.showTafsir) loadPageTafsirIfNeeded(currentPage.value)
  window.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  stopRecitation()
  window.removeEventListener('keydown', onKey)
})
</script>

<template>
  <div class="page quran-page">
    <!-- شريط واحد هادئ ونظيف فقط فوق ورقة المصحف -->
    <header class="mushaf-bar">
      <button type="button" class="index-btn" @click="openDrawer('index')">
        <i class="ti ti-list-search" aria-hidden="true"></i>
        <span class="ib-txt">
          <strong>{{ pageSurahs[0] || 'المصحف الشريف' }}</strong>
          <small>صفحة {{ toArDigits(currentPage) }} · الجزء {{ toArDigits(pageJuz) }}</small>
        </span>
        <i class="ti ti-chevron-down" aria-hidden="true"></i>
      </button>

      <select v-model="qs.prefs.riwayahId" class="riwayah-sel" aria-label="اختر الرواية">
        <option v-for="rw in READING_RIWAYAT" :key="rw.id" :value="rw.id">
          رواية {{ rw.short }}
        </option>
      </select>

      <div class="bar-acts">
        <button
          type="button"
          class="icon-pill"
          :class="{ on: isReciting }"
          :title="isReciting ? 'إيقاف التلاوة' : 'استماع للصفحة'"
          @click="toggleRecitation"
        >
          <i class="ti" :class="isReciting ? 'ti-player-pause' : 'ti-player-play'"></i>
          <span class="hide-xs">{{ isReciting ? 'إيقاف' : 'استماع' }}</span>
        </button>

        <button
          type="button"
          class="icon-pill"
          :class="{ on: drawer === 'downloads' }"
          title="التحميل وصوتيات القراءات"
          @click="openDrawer('downloads')"
        >
          <i class="ti ti-download"></i>
          <span class="hide-xs">تحميل</span>
        </button>

        <button
          type="button"
          class="icon-pill"
          :class="{ on: drawer === 'settings' }"
          title="إعدادات القراءة والتلاوة"
          aria-label="إعدادات القراءة"
          @click="openDrawer('settings')"
        >
          <i class="ti ti-adjustments-horizontal"></i>
        </button>
      </div>
    </header>

    <div v-if="riwayahSwitching" class="subtle-banner">جاري التبديل إلى الرواية المختارة...</div>

    <!-- ورقة المصحف (90% من الشاشة — هادئة وفورية بدون أي زحمة) -->
    <div v-if="initLoading" class="empty">جاري تجهيز المصحف للمرة الأولى (بعدها التقليب فوري بدون تحميل)...</div>
    <div v-else-if="initError" class="empty">
      <p>{{ initError }}</p>
      <button type="button" class="btn btn-primary" style="margin-top:10px" @click="initMushaf">إعادة المحاولة</button>
    </div>

    <article
      v-else
      class="mushaf-sheet"
      :class="`theme-${qs.prefs.theme}`"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
    >
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

        <!-- بطاقة الآية عند الضغط عليها -->
        <div v-if="selectedAyahObj" class="ayah-popover">
          <div class="ap-head">
            <strong>{{ selectedAyahObj.surahName }} — آية {{ toArDigits(selectedAyahObj.numberInSurah) }}</strong>
            <button type="button" class="mini-btn" @click="selectedAyahKey = null"><i class="ti ti-x"></i></button>
          </div>
          <div class="ap-actions">
            <button type="button" class="pill-btn on" @click="playAyahAt(pageAyahs.findIndex(x => x.key === selectedAyahObj.key))">
              <i class="ti ti-player-play"></i> تلاوة
            </button>
            <button
              type="button"
              class="pill-btn"
              :class="{ on: qs.isBookmarked(selectedAyahObj.surahNumber, selectedAyahObj.numberInSurah) }"
              @click="qs.toggleBookmark({ surah: selectedAyahObj.surahNumber, surahName: selectedAyahObj.surahName, ayah: selectedAyahObj.numberInSurah, text: selectedAyahObj.text })"
            >
              <i class="ti ti-bookmark"></i> {{ qs.isBookmarked(selectedAyahObj.surahNumber, selectedAyahObj.numberInSurah) ? 'محفوظة' : 'علامة' }}
            </button>
            <button type="button" class="pill-btn" @click="copyAyah(selectedAyahObj)">
              <i class="ti" :class="copiedAyah === selectedAyahObj.key ? 'ti-check' : 'ti-copy'"></i> نسخ
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

      <!-- وضع آية بآية مع التفسير -->
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
                @click="qs.toggleBookmark({ surah: a.surahNumber, surahName: a.surahName, ayah: a.numberInSurah, text: a.text })"
              >
                <i class="ti" :class="qs.isBookmarked(a.surahNumber, a.numberInSurah) ? 'ti-bookmark-filled' : 'ti-bookmark'"></i>
              </button>
              <button type="button" class="vc-btn" @click="copyAyah(a)"><i class="ti ti-copy"></i></button>
            </div>
          </div>
          <p class="vc-text" :style="{ fontSize: qs.prefs.fontSize + 'px' }">{{ a.text }} <span class="ayah-end">﴿{{ toArDigits(a.numberInSurah) }}﴾</span></p>
          <p v-if="qs.prefs.showTafsir && a.tafsir" class="vc-tafsir">{{ a.tafsir }}</p>
        </div>
      </div>

      <!-- شريط تقليب الصفحات النظيف -->
      <footer class="page-nav">
        <button type="button" class="nav-btn" :disabled="currentPage <= 1" @click="goToPage(currentPage - 1)">
          <i class="ti ti-chevron-right"></i> السابقة
        </button>
        <span class="page-badge">{{ toArDigits(currentPage) }} / ٦٠٤</span>
        <button type="button" class="nav-btn primary" :disabled="currentPage >= 604" @click="goToPage(currentPage + 1)">
          التالية <i class="ti ti-chevron-left"></i>
        </button>
      </footer>
    </article>

    <!-- ==================== الأدراج النظيفة عند الطلب فقط ==================== -->
    <Transition name="fade">
      <div v-if="drawer" class="drawer-backdrop" @click="drawer = null"></div>
    </Transition>

    <Transition name="slide-up">
      <aside v-if="drawer" class="drawer-panel" role="dialog" aria-modal="true">
        <div class="d-head">
          <strong>
            {{ drawer === 'index' ? 'فهرس المصحف والبحث' : drawer === 'settings' ? 'إعدادات القراءة والتلاوة' : 'التحميل وصوتيات القراءات' }}
          </strong>
          <button type="button" class="mini-btn" aria-label="إغلاق" @click="drawer = null"><i class="ti ti-x"></i></button>
        </div>

        <!-- 1. درج الفهرس (السور / الأجزاء / البحث / العلامات / رقم الصفحة) -->
        <div v-if="drawer === 'index'" class="d-body">
          <form class="jump-row" @submit.prevent="goToPage(pageInput)">
            <input v-model.number="pageInput" type="number" min="1" max="604" class="d-input" placeholder="رقم الصفحة (١–٦٠٤)" />
            <button type="submit" class="btn btn-primary">انتقال</button>
          </form>

          <div class="d-tabs">
            <button :class="{ on: indexTab === 'surahs' }" @click="indexTab = 'surahs'">السور</button>
            <button :class="{ on: indexTab === 'juzs' }" @click="indexTab = 'juzs'">الأجزاء</button>
            <button :class="{ on: indexTab === 'search' }" @click="indexTab = 'search'">بحث فوري</button>
            <button :class="{ on: indexTab === 'bookmarks' }" @click="indexTab = 'bookmarks'">العلامات ({{ toArDigits(qs.bookmarks.length) }})</button>
          </div>

          <template v-if="indexTab === 'surahs'">
            <input v-model="surahSearch" type="search" class="d-input" placeholder="ابحث باسم السورة..." />
            <div class="d-list">
              <button v-for="s in filteredSurahs" :key="s.number" type="button" class="d-row" @click="openSurahPage(s.number)">
                <span class="sc-num">{{ toArDigits(s.number) }}</span>
                <span class="dr-info"><strong>{{ s.name }}</strong><small>{{ toArDigits(s.numberOfAyahs) }} آية · ص {{ toArDigits(s.startPage) }}</small></span>
              </button>
            </div>
          </template>

          <div v-else-if="indexTab === 'juzs'" class="d-list">
            <button v-for="j in QURAN_JUZS" :key="j.juz" type="button" class="d-row" @click="openJuzPage(j.juz)">
              <span class="sc-num">{{ toArDigits(j.juz) }}</span>
              <span class="dr-info"><strong>{{ j.name }}</strong><small>صفحة {{ toArDigits(JUZ_START_PAGES[j.juz - 1]) }}</small></span>
            </button>
          </div>

          <template v-else-if="indexTab === 'search'">
            <input v-model="quranQuery" type="search" class="d-input" placeholder="اكتب كلمة للبحث الفوري في كامل القرآن..." />
            <div class="d-list">
              <button v-for="m in searchResults" :key="m.key" type="button" class="d-row col" @click="goToPage(m.page, m.key)">
                <p class="res-text">{{ m.text }}</p>
                <small>{{ m.surahName }} · آية {{ toArDigits(m.numberInSurah) }} · ص {{ toArDigits(m.page) }}</small>
              </button>
            </div>
          </template>

          <div v-else class="d-list">
            <div v-if="!qs.bookmarks.length" class="empty">لا توجد علامات محفوظة بعد.</div>
            <div v-for="b in qs.bookmarks" :key="b.key" class="d-row">
              <button type="button" class="dr-info" style="text-align:right;flex:1" @click="openSurahPage(b.surah, b.ayah)">
                <strong>{{ b.surahName }} — آية {{ toArDigits(b.ayah) }}</strong>
                <small>{{ b.text }}</small>
              </button>
              <button type="button" class="mini-btn" @click="qs.removeBookmark(b.key)"><i class="ti ti-trash"></i></button>
            </div>
          </div>
        </div>

        <!-- 2. درج الإعدادات -->
        <div v-else-if="drawer === 'settings'" class="d-body">
          <div class="s-group">
            <label>طريقة العرض</label>
            <div class="seg full">
              <button :class="{ on: qs.prefs.viewMode === 'mushaf' }" @click="qs.prefs.viewMode = 'mushaf'">صفحة مصحف متصلة</button>
              <button :class="{ on: qs.prefs.viewMode === 'verses' }" @click="qs.prefs.viewMode = 'verses'">آية بآية مع التفسير</button>
            </div>
          </div>

          <div class="s-group">
            <label>لون الصفحة</label>
            <div class="seg full">
              <button :class="{ on: qs.prefs.theme === 'light' }" @click="qs.prefs.theme = 'light'">أبيض</button>
              <button :class="{ on: qs.prefs.theme === 'paper' }" @click="qs.prefs.theme = 'paper'">ورقي دافئ</button>
              <button :class="{ on: qs.prefs.theme === 'dark' }" @click="qs.prefs.theme = 'dark'">ليلي مريح</button>
            </div>
          </div>

          <div class="s-group">
            <label>حجم الخط: {{ toArDigits(qs.prefs.fontSize) }}</label>
            <input v-model.number="qs.prefs.fontSize" type="range" min="20" max="42" step="2" style="width:100%" />
          </div>

          <div class="s-group">
            <label>القارئ (للتلاوة المتزامنة)</label>
            <select v-model="qs.prefs.reciterId" class="d-input">
              <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <div class="s-group">
            <label>التفسير</label>
            <div style="display:flex;gap:8px;align-items:center">
              <select v-model="qs.prefs.tafsirId" class="d-input" style="flex:1">
                <option v-for="t in QURAN_TAFSIRS" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <button type="button" class="pill-btn" :class="{ on: qs.prefs.showTafsir }" @click="qs.prefs.showTafsir = !qs.prefs.showTafsir">
                {{ qs.prefs.showTafsir ? 'مُفعّل' : 'مخفي' }}
              </button>
            </div>
          </div>

          <div class="s-group">
            <label>تكرار الآية للحفظ</label>
            <div class="seg full">
              <button :class="{ on: qs.prefs.repeatCount === 1 }" @click="qs.prefs.repeatCount = 1">مرة</button>
              <button :class="{ on: qs.prefs.repeatCount === 3 }" @click="qs.prefs.repeatCount = 3">٣ مرات</button>
              <button :class="{ on: qs.prefs.repeatCount === 5 }" @click="qs.prefs.repeatCount = 5">٥ مرات</button>
              <button :class="{ on: qs.prefs.repeatCount === 0 }" @click="qs.prefs.repeatCount = 0">مستمر ∞</button>
            </div>
          </div>
        </div>

        <!-- 3. درج التحميل وصوتيات القراءات -->
        <div v-else-if="drawer === 'downloads'" class="d-body">
          <div class="d-tabs">
            <button :class="{ on: dlTab === 'range' }" @click="dlTab = 'range'">من آية إلى آية</button>
            <button :class="{ on: dlTab === 'juz' }" @click="dlTab = 'juz'">أجزاء وسور</button>
            <button :class="{ on: dlTab === 'qiraat' }" @click="dlTab = 'qiraat'; !riwayatAudio.length && loadRiwayatAudio()">صوتيات ٢٠ رواية</button>
          </div>

          <template v-if="dlTab === 'range'">
            <div class="s-group">
              <label>القارئ</label>
              <select v-model="dlReciter" class="d-input">
                <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
            <div class="s-group">
              <label>السورة</label>
              <select v-model.number="rangeSurah" class="d-input">
                <option v-for="s in surahs" :key="s.number" :value="s.number">{{ toArDigits(s.number) }}. {{ s.name }} ({{ toArDigits(s.numberOfAyahs) }} آية)</option>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <div class="s-group"><label>من آية</label><input v-model.number="rangeFrom" type="number" min="1" :max="rangeSurahObj.numberOfAyahs" class="d-input" /></div>
              <div class="s-group"><label>إلى آية</label><input v-model.number="rangeTo" type="number" :min="rangeFrom" :max="rangeSurahObj.numberOfAyahs" class="d-input" /></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
              <button type="button" class="btn btn-primary" :disabled="rangeCount <= 0" @click="downloadAyahRangeMp3">
                <i class="ti ti-download"></i> تحميل MP3 مدمج ({{ toArDigits(rangeCount) }} آية)
              </button>
              <button type="button" class="btn btn-soft" :disabled="rangeCount <= 0" @click="downloadAyahRangeText">
                <i class="ti ti-file-text"></i> نص TXT
              </button>
            </div>
            <p v-if="rangeMsg" class="subtle-banner">{{ rangeMsg }}</p>
          </template>

          <template v-else-if="dlTab === 'juz'">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
              <select v-model="dlReciter" class="d-input">
                <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
              <select v-model.number="dlJuz" class="d-input">
                <option v-for="j in QURAN_JUZS" :key="j.juz" :value="j.juz">{{ toArDigits(j.juz) }}. {{ j.name }}</option>
              </select>
            </div>
            <div class="d-list">
              <div v-for="sNum in dlSurahNumbers" :key="sNum" class="d-row">
                <span class="sc-num">{{ toArDigits(sNum) }}</span>
                <span class="dr-info"><strong>{{ surahByNum(sNum)?.name || `سورة ${sNum}` }}</strong></span>
                <a :href="surahMp3Url(dlReciter, sNum)" target="_blank" rel="noopener" download class="pill-btn on"><i class="ti ti-download"></i> MP3</a>
              </div>
            </div>
          </template>

          <template v-else>
            <select :value="selectedRewayaId" class="d-input" @change="loadQiraatReciters(Number($event.target.value))">
              <option v-for="rw in riwayatAudio" :key="rw.id" :value="rw.id">{{ rw.name }}</option>
            </select>
            <select :value="selectedQiraatReciter?.id || ''" class="d-input" @change="selectedQiraatReciter = qiraatReciters.find(r => r.id === Number($event.target.value))">
              <option v-for="r in qiraatReciters" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
            <div v-if="loadingQiraat" class="empty">جاري تحميل الرواية...</div>
            <div v-else class="d-list">
              <div v-for="s in qiraatSurahs" :key="s.number" class="d-row">
                <span class="sc-num">{{ toArDigits(s.number) }}</span>
                <span class="dr-info"><strong>{{ s.name }}</strong></span>
                <button type="button" class="pill-btn" @click="playQiraatSurah(s)"><i class="ti ti-player-play"></i></button>
                <a :href="s.url" target="_blank" rel="noopener" download class="pill-btn on"><i class="ti ti-download"></i></a>
              </div>
            </div>
          </template>
        </div>
      </aside>
    </Transition>
  </div>
</template>

<style scoped>
.quran-page { gap: 14px; padding-top: 16px; }

/* شريط واحد نظيف فقط */
.mushaf-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  padding: 10px 12px; border-radius: var(--radius);
  background: var(--card); border: 1px solid var(--border); box-shadow: var(--shadow-sm);
  max-width: 820px; width: 100%; margin: 0 auto;
}
.index-btn {
  display: flex; align-items: center; gap: 10px;
  padding: 6px 12px; border-radius: 10px; background: var(--surface);
  flex: 1; min-width: 170px; text-align: right; transition: background .15s ease;
}
.index-btn:hover { background: var(--surface-2); }
.index-btn .ti { font-size: 19px; color: var(--primary); }
.ib-txt { display: flex; flex-direction: column; flex: 1; min-width: 0; line-height: 1.3; }
.ib-txt strong { font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ib-txt small { font-size: 12px; color: var(--text-3); }

.riwayah-sel {
  height: 40px; padding: 0 12px; border-radius: 10px;
  border: 1px solid var(--border); background: var(--surface); color: var(--text);
  font-family: inherit; font-size: 13.5px; font-weight: 600;
}
.bar-acts { display: flex; align-items: center; gap: 6px; }
.icon-pill {
  display: inline-flex; align-items: center; gap: 5px;
  height: 40px; padding: 0 12px; border-radius: 10px;
  background: var(--surface); color: var(--text-2); font-size: 13.5px; font-weight: 500;
  transition: .15s var(--ease);
}
.icon-pill:hover { background: var(--surface-2); color: var(--text); }
.icon-pill.on { background: var(--primary); color: var(--on-primary); }
.icon-pill .ti { font-size: 19px; }

.subtle-banner {
  max-width: 820px; width: 100%; margin: 0 auto; text-align: center;
  font-size: 13px; color: var(--primary); background: var(--primary-soft);
  padding: 7px 12px; border-radius: 10px;
}

/* ورقة المصحف الهادئة */
.mushaf-sheet {
  border-radius: 20px; padding: 28px 30px; border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 20px;
  max-width: 820px; width: 100%; margin: 0 auto;
  box-shadow: var(--shadow-md);
}
.mushaf-sheet.theme-light { background: #ffffff; color: #1b2420; }
.mushaf-sheet.theme-paper { background: #fbf6ea; color: #2b2113; border-color: #e9dec5; }
.mushaf-sheet.theme-dark { background: #141c19; color: #e8eee9; border-color: #273530; }

.mushaf-block { display: flex; flex-direction: column; gap: 12px; }
.surah-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 10px 18px; border-radius: 14px;
  background: rgba(38, 96, 79, 0.08); border: 1px solid rgba(38, 96, 79, 0.2);
}
.surah-banner h2 { font-family: 'Amiri Quran', var(--font-quote); font-size: 25px; margin: 0; color: var(--primary); }
.s-meta { font-size: 13px; opacity: .75; font-weight: 500; }
.basmala { text-align: center; font-family: 'Amiri Quran', var(--font-quote); font-size: 28px; margin: 0; color: var(--primary); }

.mushaf-flow { font-family: 'Amiri Quran', var(--font-quote); line-height: 2.35; text-align: justify; text-align-last: center; }
.ayah-inline { cursor: pointer; border-radius: 8px; padding: 2px 3px; transition: background .12s ease; }
.ayah-inline:hover { background: rgba(38, 96, 79, 0.09); }
.ayah-inline.active { background: rgba(38, 96, 79, 0.22); }
.ayah-inline.selected { background: rgba(217, 155, 38, 0.22); }
.ayah-inline.marked .ayah-end { color: #d97706; }
.ayah-end { color: var(--primary); font-size: 0.78em; margin-inline: 4px; white-space: nowrap; }

.ayah-popover {
  position: sticky; bottom: 84px; z-index: 12;
  background: var(--card); color: var(--text);
  border: 1px solid var(--primary); border-radius: 16px; padding: 14px 16px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16); display: flex; flex-direction: column; gap: 10px;
}
.ap-head { display: flex; align-items: center; justify-content: space-between; font-size: 14px; }
.ap-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.ap-tafsir { background: var(--surface); padding: 10px 12px; border-radius: 10px; font-size: 14px; line-height: 1.8; color: var(--text-2); }
.ap-tafsir small { display: block; color: var(--primary); font-weight: 600; margin-bottom: 4px; }

.verses-list { display: flex; flex-direction: column; gap: 12px; }
.verse-card { padding: 16px; border-radius: 14px; border: 1px solid rgba(128, 128, 128, 0.2); display: flex; flex-direction: column; gap: 10px; }
.verse-card.active { border-color: var(--primary); background: rgba(38, 96, 79, 0.08); }
.vc-top { display: flex; align-items: center; justify-content: space-between; }
.vc-num { font-size: 12.5px; opacity: .7; font-weight: 600; }
.vc-acts { display: flex; gap: 4px; }
.vc-btn { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-size: 17px; opacity: .8; }
.vc-btn:hover, .vc-btn.on { background: var(--primary); color: var(--on-primary); opacity: 1; }
.vc-text { font-family: 'Amiri Quran', var(--font-quote); line-height: 2.2; margin: 0; }
.vc-tafsir { margin: 0; padding-top: 10px; border-top: 1px dashed rgba(128, 128, 128, 0.25); font-size: 15px; line-height: 1.85; opacity: .85; }

.page-nav {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding-top: 14px; border-top: 1px solid rgba(128, 128, 128, 0.2);
}
.nav-btn {
  display: inline-flex; align-items: center; gap: 6px;
  height: 40px; padding: 0 18px; border-radius: 999px;
  background: var(--surface); color: var(--text); font-size: 14px; font-weight: 600;
}
.nav-btn.primary { background: var(--primary); color: var(--on-primary); }
.nav-btn:disabled { opacity: .35; cursor: not-allowed; }
.page-badge { font-size: 14px; font-weight: 700; opacity: .75; }

/* الأدراج المنزلقة */
.drawer-backdrop { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.42); backdrop-filter: blur(3px); z-index: 45; }
.drawer-panel {
  position: fixed; inset-inline: 0; bottom: 0; z-index: 50;
  max-width: 620px; width: 100%; margin: 0 auto;
  max-height: 82vh; background: var(--card); border-radius: 22px 22px 0 0;
  border-top: 1px solid var(--border); box-shadow: 0 -14px 40px rgba(0, 0, 0, 0.18);
  display: flex; flex-direction: column; overflow: hidden;
}
.d-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 16px;
}
.d-body { padding: 16px 20px 26px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
.jump-row { display: flex; gap: 8px; }
.d-input {
  width: 100%; height: 42px; padding: 0 12px; border-radius: 10px;
  border: 1px solid var(--border-strong); background: var(--bg); color: var(--text);
  font-family: inherit; font-size: 14px;
}
.d-tabs { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 6px; background: var(--surface); padding: 4px; border-radius: 12px; }
.d-tabs button { height: 34px; border-radius: 9px; font-size: 13px; color: var(--text-2); font-weight: 500; }
.d-tabs button.on { background: var(--card); color: var(--primary); font-weight: 700; box-shadow: var(--shadow-sm); }

.d-list { display: flex; flex-direction: column; gap: 6px; max-height: 46vh; overflow-y: auto; }
.d-row {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px;
  background: var(--surface); border: 1px solid var(--border); text-align: right; width: 100%;
}
.d-row:hover { border-color: var(--primary); }
.d-row.col { flex-direction: column; align-items: flex-start; gap: 4px; }
.dr-info { display: flex; flex-direction: column; min-width: 0; }
.dr-info strong { font-size: 14.5px; }
.dr-info small { font-size: 12.5px; color: var(--text-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sc-num {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  background: var(--primary-soft); color: var(--primary);
  display: grid; place-items: center; font-weight: 700; font-size: 13.5px;
}
.res-text { font-family: 'Amiri Quran', var(--font-quote); font-size: 20px; line-height: 1.8; margin: 0; }

.s-group { display: flex; flex-direction: column; gap: 6px; }
.s-group label { font-size: 13.5px; font-weight: 600; color: var(--text-2); }
.seg { display: inline-flex; background: var(--surface); border: 1px solid var(--border); padding: 3px; border-radius: 10px; gap: 3px; }
.seg.full { display: grid; grid-template-columns: repeat(auto-fit, minmax(70px, 1fr)); width: 100%; }
.seg button { height: 34px; border-radius: 8px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--primary); color: var(--on-primary); font-weight: 600; }

.mini-btn { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; color: var(--text-2); background: var(--surface); }
.pill-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 34px; padding: 0 12px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border); color: var(--text-2); font-size: 13px; font-weight: 500;
}
.pill-btn.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

.fade-enter-active, .fade-leave-active { transition: opacity .2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-up-enter-active, .slide-up-leave-active { transition: transform .25s var(--ease); }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }

@media (max-width: 640px) {
  .mushaf-sheet { padding: 18px 14px; border-radius: 16px; }
  .hide-xs { display: none; }
  .icon-pill { padding: 0 10px; }
}
</style>
