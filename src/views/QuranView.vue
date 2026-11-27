<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuranStore } from '@/stores/quran'
import { usePlayerStore } from '@/stores/player'
import { toArDigits } from '@/utils/format'
import {
  QURAN_RECITERS,
  QURAN_TAFSIRS,
  QURAN_JUZS,
  surahMp3Url,
  normalizeAr
} from '@/data/quran'

const route = useRoute()
const router = useRouter()
const qs = useQuranStore()
const globalPlayer = usePlayerStore()

const SECTIONS = [
  { id: 'read', label: 'القراءة', icon: 'ti-book-2' },
  { id: 'surahs', label: 'السور (١١٤)', icon: 'ti-list-numbers' },
  { id: 'juzs', label: 'الأجزاء (٣٠)', icon: 'ti-books' },
  { id: 'downloads', label: 'مركز التحميل', icon: 'ti-download' },
  { id: 'search', label: 'بحث وعلامات', icon: 'ti-search' }
]

const activeSection = ref(SECTIONS.some((s) => s.id === route.query.tab) ? route.query.tab : 'read')
function setSection(id) {
  activeSection.value = id
  router.replace({ query: { ...route.query, tab: id } })
}

/* ==================== 1. قائمة الـ 114 سورة ==================== */
const surahs = ref([])
const surahSearch = ref('')
const revFilter = ref('all') // all | Meccan | Medinan

async function loadSurahsList() {
  try {
    const res = await fetch('https://api.alquran.cloud/v1/surah')
    const json = await res.json()
    surahs.value = json.data || []
  } catch {}
}

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

/* ==================== 2. قارئ السورة (النص + الصوت + التفسير) ==================== */
const currentSurahNum = ref(Number(route.query.surah) || qs.lastRead?.surah || 1)
const currentSurah = ref(null) // { number, name, revelationType, numberOfAyahs }
const ayahs = ref([]) // [{ numberInSurah, text, audio, tafsir, page, juz }]
const loadingSurah = ref(false)
const surahError = ref('')
const selectedAyahNum = ref(null) // للـ popover في وضع المصحف
const copiedAyah = ref(null)

async function loadSurah(num, scrollToAyah = null) {
  const n = Math.min(114, Math.max(1, Number(num) || 1))
  currentSurahNum.value = n
  stopRecitation()
  loadingSurah.value = true
  surahError.value = ''
  selectedAyahNum.value = null

  try {
    const recId = qs.prefs.reciterId || 'ar.husary'
    const tafId = qs.prefs.tafsirId || 'ar.muyassar'
    const url = `https://api.alquran.cloud/v1/surah/${n}/editions/quran-uthmani,${recId},${tafId}`
    const res = await fetch(url)
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()
    const [uthmani, audioEd, tafsirEd] = json.data

    currentSurah.value = {
      number: uthmani.number,
      name: uthmani.name,
      englishName: uthmani.englishName,
      revelationType: uthmani.revelationType,
      numberOfAyahs: uthmani.numberOfAyahs
    }

    ayahs.value = uthmani.ayahs.map((a, idx) => {
      // إزالة البسملة المدمجة في أول آية لغير الفاتحة والتوبة حتى نعرضها مستقلّة
      let txt = a.text
      if (n !== 1 && n !== 9 && a.numberInSurah === 1) {
        txt = txt.replace(/^بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ\s*/u, '').trim() || a.text
      }
      return {
        number: a.number,
        numberInSurah: a.numberInSurah,
        text: txt,
        audio: audioEd?.ayahs?.[idx]?.audio || '',
        tafsir: tafsirEd?.ayahs?.[idx]?.text || '',
        page: a.page,
        juz: a.juz
      }
    })

    qs.setLastRead({
      surah: n,
      surahName: uthmani.name,
      ayah: scrollToAyah || 1,
      page: ayahs.value[0]?.page || 1
    })

    if (scrollToAyah) {
      await nextTick()
      scrollToAyahEl(scrollToAyah)
    }
  } catch {
    surahError.value = 'تعذّر تحميل السورة الآن. تأكد من الاتصال بالإنترنت وحاول مرة أخرى.'
  } finally {
    loadingSurah.value = false
  }
}

function openSurah(num, ayahNum = null) {
  activeSection.value = 'read'
  router.replace({ query: { tab: 'read', surah: num } })
  loadSurah(num, ayahNum)
}

function scrollToAyahEl(ayahNum) {
  const el = document.getElementById(`ayah-${ayahNum}`)
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

// إعادة تحميل التلاوة/التفسير عند تغيير القارئ أو التفسير
watch(() => [qs.prefs.reciterId, qs.prefs.tafsirId], () => {
  if (currentSurah.value) loadSurah(currentSurahNum.value)
})

/* ==================== 3. مشغّل التلاوة المتزامن آية بآية ==================== */
const ayahAudio = typeof Audio !== 'undefined' ? new Audio() : null
const playingAyahIdx = ref(-1)
const isReciting = ref(false)
let repeatDone = 0

if (ayahAudio) {
  ayahAudio.addEventListener('play', () => {
    isReciting.value = true
    // إيقاف المشغّل السفلي العام لو كان شغّالاً حتى لا يتداخل الصوتان
    if (globalPlayer.playing) globalPlayer.pause()
  })
  ayahAudio.addEventListener('pause', () => {
    isReciting.value = false
  })
  ayahAudio.addEventListener('ended', () => {
    const rep = qs.prefs.repeatCount
    if (rep === 0) {
      // تكرار لا نهائي للحفظ
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
    if (playingAyahIdx.value + 1 < ayahs.value.length) {
      playAyahAt(playingAyahIdx.value + 1)
    } else {
      isReciting.value = false
      playingAyahIdx.value = -1
    }
  })
}

function playAyahAt(idx) {
  if (!ayahAudio || !ayahs.value[idx]?.audio) return
  playingAyahIdx.value = idx
  repeatDone = 0
  const a = ayahs.value[idx]
  ayahAudio.src = a.audio
  ayahAudio.playbackRate = qs.prefs.rate || 1
  ayahAudio.play().catch(() => (isReciting.value = false))
  qs.setLastRead({
    surah: currentSurahNum.value,
    surahName: currentSurah.value?.name || '',
    ayah: a.numberInSurah,
    page: a.page
  })
  nextTick(() => scrollToAyahEl(a.numberInSurah))
}

function toggleRecitation() {
  if (!ayahAudio || !ayahs.value.length) return
  if (isReciting.value) {
    ayahAudio.pause()
  } else if (playingAyahIdx.value >= 0) {
    ayahAudio.play().catch(() => {})
  } else {
    playAyahAt(0)
  }
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
  selectedAyahNum.value ? ayahs.value.find((a) => a.numberInSurah === selectedAyahNum.value) : null
)

async function copyAyah(a) {
  const txt = `﴿${a.text}﴾ [${currentSurah.value?.name}: ${toArDigits(a.numberInSurah)}]`
  try {
    await navigator.clipboard.writeText(txt)
    copiedAyah.value = a.numberInSurah
    setTimeout(() => (copiedAyah.value = null), 1800)
  } catch {}
}

async function shareAyah(a) {
  const txt = `﴿${a.text}﴾\n— ${currentSurah.value?.name} (آية ${toArDigits(a.numberInSurah)})`
  if (navigator.share) {
    try { await navigator.share({ title: currentSurah.value?.name, text: txt, url: window.location.href }); return } catch {}
  }
  copyAyah(a)
}

/* ==================== 4. مركز التحميل (سور وأجزاء + نص أوفلاين) ==================== */
const dlReciter = ref(qs.prefs.reciterId || 'ar.husary')
const dlJuz = ref(30) // الافتراضي جزء عمّ
const dlProgress = ref('')

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

function downloadCurrentSurahText() {
  if (!currentSurah.value || !ayahs.value.length) return
  const lines = [
    `${currentSurah.value.name} (${currentSurah.value.numberOfAyahs} آية)`,
    '='.repeat(40),
    ...ayahs.value.map((a) => `(${a.numberInSurah}) ${a.text}`)
  ]
  const blob = new Blob([lines.join('\n\n')], { type: 'text/plain;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  triggerDownload(url, `surah-${currentSurah.value.number}.txt`)
  setTimeout(() => URL.revokeObjectURL(url), 3000)
}

async function downloadJuzText(juzNum) {
  dlProgress.value = `جاري تجهيز نص الجزء ${toArDigits(juzNum)}...`
  try {
    const res = await fetch(`https://api.alquran.cloud/v1/juz/${juzNum}/quran-uthmani`)
    const json = await res.json()
    const list = json.data?.ayahs || []
    const lines = [`القرآن الكريم — الجزء ${juzNum}`, '='.repeat(40)]
    let lastS = null
    for (const a of list) {
      if (a.surah?.number !== lastS) {
        lastS = a.surah?.number
        lines.push(`\n--- ${a.surah?.name || ''} ---`)
      }
      lines.push(`(${a.numberInSurah}) ${a.text}`)
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    triggerDownload(url, `quran-juz-${juzNum}.txt`)
    setTimeout(() => URL.revokeObjectURL(url), 3000)
    dlProgress.value = `تم تنزيل نص الجزء ${toArDigits(juzNum)} بنجاح.`
  } catch {
    dlProgress.value = 'تعذّر تحميل النص الآن.'
  }
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

/* ==================== 5. البحث في آيات القرآن الكريم ==================== */
const quranQuery = ref('')
const searchResults = ref([])
const searchLoading = ref(false)
const searchError = ref('')

async function runQuranSearch() {
  const term = quranQuery.value.trim()
  if (term.length < 2) return
  searchLoading.value = true
  searchError.value = ''
  searchResults.value = []
  try {
    const res = await fetch(`https://api.alquran.cloud/v1/search/${encodeURIComponent(term)}/all/quran-simple-clean`)
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()
    searchResults.value = (json.data?.matches || []).slice(0, 60)
  } catch {
    searchError.value = 'لم يُعثر على نتائج مطابقة لهذا البحث.'
  } finally {
    searchLoading.value = false
  }
}

onMounted(() => {
  loadSurahsList()
  loadSurah(currentSurahNum.value)
})

onBeforeUnmount(() => {
  stopRecitation()
})
</script>

<template>
  <div class="page quran-page">
    <header class="page-head q-head">
      <div>
        <h1>المصحف الشريف</h1>
        <p>قراءة متكاملة بالرسم العثماني، وتلاوة متزامنة آية بآية لـ ٩ قراء، وتفسير وبحث وتحميل للأجزاء والسور.</p>
      </div>
      <button
        v-if="qs.lastRead && activeSection !== 'read'"
        type="button"
        class="btn btn-primary"
        @click="openSurah(qs.lastRead.surah, qs.lastRead.ayah)"
      >
        <i class="ti ti-bookmark" aria-hidden="true"></i>
        متابعة: {{ qs.lastRead.surahName }} ({{ toArDigits(qs.lastRead.ayah) }})
      </button>
    </header>

    <!-- شريط الأقسام -->
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

    <!-- ==================== 1. شاشة القراءة ==================== -->
    <section v-if="activeSection === 'read'" class="reader-wrap">
      <!-- شريط التحكم العلوي للقارئ -->
      <div class="ctrl-card">
        <div class="ctrl-row">
          <select
            :value="currentSurahNum"
            class="q-select main-sel"
            aria-label="اختر السورة"
            @change="openSurah(Number($event.target.value))"
          >
            <option v-for="s in surahs" :key="s.number" :value="s.number">
              {{ toArDigits(s.number) }}. {{ s.name }} ({{ toArDigits(s.numberOfAyahs) }} آية)
            </option>
          </select>

          <select v-model="qs.prefs.reciterId" class="q-select" aria-label="اختر القارئ">
            <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>

          <select v-model="qs.prefs.tafsirId" class="q-select" aria-label="اختر التفسير">
            <option v-for="t in QURAN_TAFSIRS" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>

        <div class="ctrl-row bar2">
          <!-- وضع العرض -->
          <div class="seg" role="group" aria-label="وضع العرض">
            <button :class="{ on: qs.prefs.viewMode === 'mushaf' }" @click="qs.prefs.viewMode = 'mushaf'">وضع المصحف</button>
            <button :class="{ on: qs.prefs.viewMode === 'verses' }" @click="qs.prefs.viewMode = 'verses'">آية بآية</button>
          </div>

          <!-- لون الورق -->
          <div class="seg" role="group" aria-label="سمة القراءة">
            <button :class="{ on: qs.prefs.theme === 'light' }" @click="qs.prefs.theme = 'light'">أبيض</button>
            <button :class="{ on: qs.prefs.theme === 'paper' }" @click="qs.prefs.theme = 'paper'">ورقي</button>
            <button :class="{ on: qs.prefs.theme === 'dark' }" @click="qs.prefs.theme = 'dark'">ليلي</button>
          </div>

          <!-- حجم الخط -->
          <div class="font-ctl">
            <button type="button" class="mini-btn" aria-label="تصغير الخط" @click="qs.prefs.fontSize = Math.max(20, qs.prefs.fontSize - 2)">أ-</button>
            <span>{{ toArDigits(qs.prefs.fontSize) }}</span>
            <button type="button" class="mini-btn" aria-label="تكبير الخط" @click="qs.prefs.fontSize = Math.min(42, qs.prefs.fontSize + 2)">أ+</button>
          </div>

          <!-- التفسير -->
          <button
            type="button"
            class="pill-btn"
            :class="{ on: qs.prefs.showTafsir }"
            @click="qs.prefs.showTafsir = !qs.prefs.showTafsir"
          >
            <i class="ti ti-book" aria-hidden="true"></i> التفسير
          </button>

          <!-- تحميل السورة -->
          <a
            :href="surahMp3Url(qs.prefs.reciterId, currentSurahNum)"
            target="_blank"
            rel="noopener"
            download
            class="pill-btn"
            title="تحميل السورة كاملة بصوت القارئ المختار"
          >
            <i class="ti ti-download" aria-hidden="true"></i> تحميل MP3
          </a>

          <button type="button" class="pill-btn" @click="downloadCurrentSurahText">
            <i class="ti ti-file-text" aria-hidden="true"></i> نص TXT
          </button>
        </div>
      </div>

      <!-- شريط التلاوة المتزامنة -->
      <div class="recite-bar">
        <button type="button" class="play-btn" @click="toggleRecitation">
          <i class="ti" :class="isReciting ? 'ti-player-pause' : 'ti-player-play'" aria-hidden="true"></i>
          {{ isReciting ? 'إيقاف التلاوة' : 'استماع آية بآية' }}
        </button>

        <span v-if="playingAyahIdx >= 0" class="recite-status">
          الآية {{ toArDigits(ayahs[playingAyahIdx]?.numberInSurah) }} من {{ toArDigits(ayahs.length) }}
        </span>
        <span v-else class="recite-status muted">اضغط على أي آية للتلاوة والتفسير</span>

        <div class="recite-extra">
          <button type="button" class="mini-pill" title="تكرار الآية للحفظ" @click="cycleRepeat">
            <i class="ti ti-repeat" aria-hidden="true"></i>
            {{ qs.prefs.repeatCount === 0 ? '∞' : `${toArDigits(qs.prefs.repeatCount)}×` }}
          </button>
          <button type="button" class="mini-pill" title="سرعة التلاوة" @click="cycleRate">
            {{ toArDigits(qs.prefs.rate) }}×
          </button>
          <button v-if="playingAyahIdx >= 0" type="button" class="mini-pill" aria-label="إيقاف" @click="stopRecitation">
            <i class="ti ti-player-stop" aria-hidden="true"></i>
          </button>
        </div>
      </div>

      <!-- حالة التحميل / الخطأ -->
      <div v-if="loadingSurah" class="empty">جاري تحميل السورة والتلاوة والتفسير...</div>
      <div v-else-if="surahError" class="empty">
        <p>{{ surahError }}</p>
        <button type="button" class="btn btn-primary" style="margin-top:10px" @click="loadSurah(currentSurahNum)">إعادة المحاولة</button>
      </div>

      <!-- صفحة المصحف -->
      <article v-else-if="currentSurah" class="mushaf-sheet" :class="`theme-${qs.prefs.theme}`">
        <div class="surah-banner">
          <span class="s-meta">{{ currentSurah.revelationType === 'Meccan' ? 'مكية' : 'مدنية' }}</span>
          <h2>{{ currentSurah.name }}</h2>
          <span class="s-meta">{{ toArDigits(currentSurah.numberOfAyahs) }} آية</span>
        </div>

        <p v-if="currentSurah.number !== 1 && currentSurah.number !== 9" class="basmala">
          بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ
        </p>

        <!-- وضع المصحف المتصل -->
        <template v-if="qs.prefs.viewMode === 'mushaf'">
          <div class="mushaf-flow" :style="{ fontSize: qs.prefs.fontSize + 'px' }">
            <span
              v-for="(a, idx) in ayahs"
              :id="`ayah-${a.numberInSurah}`"
              :key="a.numberInSurah"
              class="ayah-inline"
              :class="{
                active: playingAyahIdx === idx,
                selected: selectedAyahNum === a.numberInSurah,
                marked: qs.isBookmarked(currentSurah.number, a.numberInSurah)
              }"
              @click="selectedAyahNum = selectedAyahNum === a.numberInSurah ? null : a.numberInSurah"
            >
              {{ a.text }}
              <span class="ayah-end">﴿{{ toArDigits(a.numberInSurah) }}﴾</span>
            </span>
          </div>

          <!-- لوحة إجراءات الآية المحددة -->
          <div v-if="selectedAyahObj" class="ayah-popover">
            <div class="ap-head">
              <strong>{{ currentSurah.name }} — آية {{ toArDigits(selectedAyahObj.numberInSurah) }} (صفحة {{ toArDigits(selectedAyahObj.page) }} · جزء {{ toArDigits(selectedAyahObj.juz) }})</strong>
              <button type="button" class="mini-btn" @click="selectedAyahNum = null"><i class="ti ti-x"></i></button>
            </div>
            <div class="ap-actions">
              <button type="button" class="pill-btn on" @click="playAyahAt(selectedAyahObj.numberInSurah - 1)">
                <i class="ti ti-player-play"></i> تلاوة من هنا
              </button>
              <button
                type="button"
                class="pill-btn"
                :class="{ on: qs.isBookmarked(currentSurah.number, selectedAyahObj.numberInSurah) }"
                @click="qs.toggleBookmark({ surah: currentSurah.number, surahName: currentSurah.name, ayah: selectedAyahObj.numberInSurah, text: selectedAyahObj.text })"
              >
                <i class="ti ti-bookmark"></i> {{ qs.isBookmarked(currentSurah.number, selectedAyahObj.numberInSurah) ? 'محفوظة' : 'حفظ علامة' }}
              </button>
              <button type="button" class="pill-btn" @click="copyAyah(selectedAyahObj)">
                <i class="ti" :class="copiedAyah === selectedAyahObj.numberInSurah ? 'ti-check' : 'ti-copy'"></i>
                {{ copiedAyah === selectedAyahObj.numberInSurah ? 'تم النسخ' : 'نسخ' }}
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
            v-for="(a, idx) in ayahs"
            :id="`ayah-${a.numberInSurah}`"
            :key="a.numberInSurah"
            class="verse-card"
            :class="{ active: playingAyahIdx === idx }"
          >
            <div class="vc-top">
              <span class="vc-num">{{ toArDigits(currentSurah.number) }}:{{ toArDigits(a.numberInSurah) }} · ص {{ toArDigits(a.page) }}</span>
              <div class="vc-acts">
                <button type="button" class="vc-btn" title="تلاوة" @click="playAyahAt(idx)">
                  <i class="ti" :class="playingAyahIdx === idx && isReciting ? 'ti-player-pause' : 'ti-player-play'"></i>
                </button>
                <button
                  type="button"
                  class="vc-btn"
                  :class="{ on: qs.isBookmarked(currentSurah.number, a.numberInSurah) }"
                  title="حفظ علامة"
                  @click="qs.toggleBookmark({ surah: currentSurah.number, surahName: currentSurah.name, ayah: a.numberInSurah, text: a.text })"
                >
                  <i class="ti" :class="qs.isBookmarked(currentSurah.number, a.numberInSurah) ? 'ti-bookmark-filled' : 'ti-bookmark'"></i>
                </button>
                <button type="button" class="vc-btn" title="نسخ" @click="copyAyah(a)">
                  <i class="ti" :class="copiedAyah === a.numberInSurah ? 'ti-check' : 'ti-copy'"></i>
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

        <!-- التنقل بين السور -->
        <div class="surah-nav">
          <button v-if="currentSurah.number > 1" type="button" class="btn btn-soft" @click="openSurah(currentSurah.number - 1)">
            <i class="ti ti-chevron-right"></i> السورة السابقة
          </button>
          <span v-else></span>
          <button v-if="currentSurah.number < 114" type="button" class="btn btn-primary" @click="openSurah(currentSurah.number + 1)">
            السورة التالية <i class="ti ti-chevron-left"></i>
          </button>
        </div>
      </article>
    </section>

    <!-- ==================== 2. فهرس الـ 114 سورة ==================== -->
    <section v-else-if="activeSection === 'surahs'" class="panel">
      <div class="ctrl-row">
        <div class="search-box">
          <i class="ti ti-search" aria-hidden="true"></i>
          <input v-model="surahSearch" type="search" placeholder="ابحث باسم السورة أو رقمها (مثل: الكهف، البقرة، 36)..." />
        </div>
        <div class="seg">
          <button :class="{ on: revFilter === 'all' }" @click="revFilter = 'all'">الكل</button>
          <button :class="{ on: revFilter === 'Meccan' }" @click="revFilter = 'Meccan'">مكية</button>
          <button :class="{ on: revFilter === 'Medinan' }" @click="revFilter = 'Medinan'">مدنية</button>
        </div>
      </div>

      <div class="surah-grid">
        <div v-for="s in filteredSurahs" :key="s.number" class="surah-card">
          <button type="button" class="sc-main" @click="openSurah(s.number)">
            <span class="sc-num">{{ toArDigits(s.number) }}</span>
            <span class="sc-info">
              <strong>{{ s.name }}</strong>
              <small>{{ s.revelationType === 'Meccan' ? 'مكية' : 'مدنية' }} · {{ toArDigits(s.numberOfAyahs) }} آية</small>
            </span>
          </button>
          <a
            :href="surahMp3Url(qs.prefs.reciterId, s.number)"
            target="_blank"
            rel="noopener"
            download
            class="sc-dl"
            title="تحميل MP3"
            aria-label="تحميل السورة MP3"
          >
            <i class="ti ti-download"></i>
          </a>
        </div>
      </div>
    </section>

    <!-- ==================== 3. الأجزاء (30 جزء) ==================== -->
    <section v-else-if="activeSection === 'juzs'" class="panel">
      <div class="juz-grid">
        <div v-for="j in QURAN_JUZS" :key="j.juz" class="juz-card">
          <div class="jc-head">
            <span class="sc-num">{{ toArDigits(j.juz) }}</span>
            <div>
              <strong>{{ j.name }}</strong>
              <small>يبدأ من {{ surahByNum(j.startSurah)?.name || `سورة ${j.startSurah}` }} (آية {{ toArDigits(j.startAyah) }})</small>
            </div>
          </div>
          <div class="jc-acts">
            <button type="button" class="pill-btn on" @click="openSurah(j.startSurah, j.startAyah)">
              <i class="ti ti-book-2"></i> قراءة
            </button>
            <button type="button" class="pill-btn" @click="dlJuz = j.juz; setSection('downloads')">
              <i class="ti ti-download"></i> تحميل الصوتيات
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== 4. مركز تحميل المصحف والأجزاء ==================== -->
    <section v-else-if="activeSection === 'downloads'" class="panel">
      <div class="ctrl-card">
        <div class="ctrl-row">
          <label class="dl-lbl">
            اختر القارئ:
            <select v-model="dlReciter" class="q-select">
              <option v-for="r in QURAN_RECITERS" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </label>
          <label class="dl-lbl">
            اختر الجزء المطلوب تحميله:
            <select v-model.number="dlJuz" class="q-select">
              <option v-for="j in QURAN_JUZS" :key="j.juz" :value="j.juz">
                {{ toArDigits(j.juz) }}. {{ j.name }} ({{ toArDigits(j.surahs.length) }} سورة)
              </option>
            </select>
          </label>
        </div>

        <div class="ctrl-row" style="margin-top:4px">
          <button type="button" class="btn btn-primary" @click="downloadJuzText(dlJuz)">
            <i class="ti ti-file-text"></i> تحميل نص الجزء {{ toArDigits(dlJuz) }} كاملًا (TXT أوفلاين)
          </button>
          <span v-if="dlProgress" class="recite-status">{{ dlProgress }}</span>
        </div>
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
            <a
              :href="surahMp3Url(dlReciter, sNum)"
              target="_blank"
              rel="noopener"
              download
              class="pill-btn on"
            >
              <i class="ti ti-download"></i> تحميل MP3
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== 5. البحث في القرآن والعلامات ==================== -->
    <section v-else-if="activeSection === 'search'" class="panel">
      <form class="ctrl-row" @submit.prevent="runQuranSearch">
        <div class="search-box">
          <i class="ti ti-search" aria-hidden="true"></i>
          <input v-model="quranQuery" type="search" placeholder="ابحث عن أي كلمة أو جملة في القرآن الكريم (مثل: الصبر، الوالدين، الرحمة)..." />
        </div>
        <button type="submit" class="btn btn-primary">بحث</button>
      </form>

      <p v-if="searchLoading" class="empty">جاري البحث في آيات القرآن الكريم...</p>
      <p v-else-if="searchError" class="empty">{{ searchError }}</p>
      <div v-else-if="searchResults.length" class="results-list">
        <button
          v-for="m in searchResults"
          :key="`${m.surah.number}:${m.numberInSurah}`"
          type="button"
          class="res-card"
          @click="openSurah(m.surah.number, m.numberInSurah)"
        >
          <p class="res-text">{{ m.text }}</p>
          <small>{{ m.surah.name }} — الآية {{ toArDigits(m.numberInSurah) }}</small>
        </button>
      </div>

      <div class="bm-section">
        <h3>العلامات المرجعية المحفوظة ({{ toArDigits(qs.bookmarks.length) }})</h3>
        <div v-if="!qs.bookmarks.length" class="empty">لم تحفظ أي علامات بعد. اضغط على أي آية أثناء القراءة لحفظها هنا.</div>
        <div v-else class="results-list">
          <div v-for="b in qs.bookmarks" :key="b.key" class="res-card bm-row">
            <button type="button" class="bm-open" @click="openSurah(b.surah, b.ayah)">
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
.quran-page { gap: 18px; }
.q-head { flex-direction: row; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px; }

.q-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.q-tab {
  display: inline-flex; align-items: center; gap: 7px; flex-shrink: 0;
  padding: 9px 14px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-2); font-size: 14px; font-weight: 500; transition: .15s var(--ease);
}
.q-tab.active { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

.ctrl-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px; display: flex; flex-direction: column; gap: 10px;
}
.ctrl-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.q-select {
  padding: 8px 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong); background: var(--bg); color: var(--text);
  font-family: inherit; font-size: 14px; flex: 1; min-width: 170px;
}
.q-select.main-sel { font-weight: 600; }

.seg { display: inline-flex; background: var(--bg); border: 1px solid var(--border); padding: 3px; border-radius: 999px; gap: 2px; }
.seg button { height: 30px; padding: 0 11px; border-radius: 999px; font-size: 13px; color: var(--text-2); }
.seg button.on { background: var(--primary); color: var(--on-primary); font-weight: 500; }

.font-ctl { display: inline-flex; align-items: center; gap: 6px; background: var(--bg); border: 1px solid var(--border); padding: 2px 8px; border-radius: 999px; font-size: 13px; }
.mini-btn { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; color: var(--text-2); }
.mini-btn:hover { background: var(--surface-2); color: var(--text); }
.pill-btn {
  display: inline-flex; align-items: center; gap: 5px;
  height: 34px; padding: 0 12px; border-radius: 999px;
  background: var(--bg); border: 1px solid var(--border);
  color: var(--text-2); font-size: 13px; font-weight: 500; transition: .15s var(--ease);
}
.pill-btn:hover { border-color: var(--primary); color: var(--primary); }
.pill-btn.on { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }

/* Recite bar */
.recite-bar {
  position: sticky; top: 68px; z-index: 8;
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

/* Mushaf sheet themes */
.mushaf-sheet {
  border-radius: 18px; padding: 28px 26px; border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 20px; transition: background .2s ease, color .2s ease;
}
.mushaf-sheet.theme-light { background: #ffffff; color: #1b2420; }
.mushaf-sheet.theme-paper { background: #fbf6ea; color: #2b2113; border-color: #e9dec5; }
.mushaf-sheet.theme-dark { background: #141c19; color: #e8eee9; border-color: #273530; }

.surah-banner {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 18px; border-radius: 14px;
  background: rgba(47, 107, 90, 0.09); border: 1px solid rgba(47, 107, 90, 0.22);
}
.surah-banner h2 { font-family: 'Amiri Quran', var(--font-quote); font-size: 26px; margin: 0; color: var(--primary); }
.s-meta { font-size: 13px; opacity: .75; font-weight: 500; }
.basmala { text-align: center; font-family: 'Amiri Quran', var(--font-quote); font-size: 30px; margin: 4px 0; color: var(--primary); }

.mushaf-flow {
  font-family: 'Amiri Quran', var(--font-quote);
  line-height: 2.35;
  text-align: justify;
  text-align-last: center;
}
.ayah-inline {
  cursor: pointer; border-radius: 8px; padding: 2px 3px; transition: background .15s ease;
}
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

/* Verses mode */
.verses-list { display: flex; flex-direction: column; gap: 14px; }
.verse-card {
  padding: 16px; border-radius: 14px; border: 1px solid rgba(128, 128, 128, 0.2);
  display: flex; flex-direction: column; gap: 10px;
}
.verse-card.active { border-color: var(--primary); background: rgba(47, 107, 90, 0.08); }
.vc-top { display: flex; align-items: center; justify-content: space-between; }
.vc-num { font-size: 12.5px; opacity: .7; font-weight: 600; }
.vc-acts { display: flex; gap: 4px; }
.vc-btn { width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; font-size: 17px; opacity: .8; }
.vc-btn:hover, .vc-btn.on { background: var(--primary); color: var(--on-primary); opacity: 1; }
.vc-text { font-family: 'Amiri Quran', var(--font-quote); line-height: 2.2; margin: 0; }
.vc-tafsir { margin: 0; padding-top: 10px; border-top: 1px dashed rgba(128, 128, 128, 0.25); font-size: 15px; line-height: 1.85; opacity: .85; }

.surah-nav { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid rgba(128, 128, 128, 0.2); }

/* Surahs, Juzs, Downloads, Search */
.panel { display: flex; flex-direction: column; gap: 14px; }
.search-box {
  flex: 1; min-width: 220px; display: flex; align-items: center; gap: 8px;
  height: 40px; padding: 0 14px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
}
.search-box input { flex: 1; min-width: 0; border: 0; background: none; outline: none; font-size: 14.5px; }
.surah-grid, .juz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px; }
.surah-card {
  display: flex; align-items: center; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border); overflow: hidden;
}
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

.juz-card {
  padding: 14px; border-radius: var(--radius); background: var(--surface); border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 12px;
}
.jc-head { display: flex; align-items: center; gap: 10px; }
.jc-head strong { display: block; font-size: 15px; }
.jc-head small { font-size: 12.5px; color: var(--text-3); }
.jc-acts { display: flex; gap: 8px; flex-wrap: wrap; }

.dl-lbl { display: flex; flex-direction: column; gap: 5px; font-size: 13px; color: var(--text-2); flex: 1; min-width: 220px; }
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
  .q-tabs { flex-wrap: nowrap; overflow-x: auto; margin-inline: -16px; padding: 2px 16px 6px; scrollbar-width: none; }
  .q-tabs::-webkit-scrollbar { display: none; }
  .mushaf-sheet { padding: 18px 14px; }
  .recite-bar { top: 60px; }
}
</style>
