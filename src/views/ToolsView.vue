<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePlayerStore } from '@/stores/player'
import { toArDigits } from '@/utils/format'
import {
  RADIO_STATIONS,
  PRAYER_CITIES,
  AZKAR_MORNING,
  AZKAR_EVENING,
  TASBIH_PHRASES
} from '@/data/muslimTools'

const route = useRoute()
const router = useRouter()
const player = usePlayerStore()

const TABS = [
  { id: 'radio', label: 'إذاعة القرآن', icon: 'ti-radio' },
  { id: 'prayer', label: 'مواقيت الصلاة', icon: 'ti-clock' },
  { id: 'ramadan', label: 'كم باقي على رمضان', icon: 'ti-moon-stars' },
  { id: 'azkar', label: 'الأذكار', icon: 'ti-sun-high' },
  { id: 'tasbih', label: 'السبحة', icon: 'ti-circles' },
  { id: 'zakat', label: 'حاسبة الزكاة', icon: 'ti-calculator' }
]

const validTab = (t) => (TABS.some((x) => x.id === t) ? t : 'radio')
const activeTab = ref(validTab(route.query.tab))

watch(() => route.query.tab, (t) => {
  if (t) activeTab.value = validTab(t)
})
function selectTab(id) {
  activeTab.value = id
  router.replace({ query: { ...route.query, tab: id } })
}

/* ==================== 1. إذاعة القرآن ==================== */
function playStation(st) {
  player.play(st)
}

/* ==================== 2. مواقيت الصلاة ==================== */
const PRAYER_KEY = 'basaer:prayer-city'
const selectedCityIdx = ref(Number(localStorage.getItem(PRAYER_KEY) || 0))
const timings = ref(null)
const hijriDate = ref('')
const prayerLoading = ref(false)
const prayerError = ref('')
const gpsLabel = ref('')
const nowTick = ref(Date.now())

const PRAYER_NAMES = [
  { key: 'Fajr', label: 'الفجر', icon: 'ti-sunrise' },
  { key: 'Sunrise', label: 'الشروق', icon: 'ti-sun' },
  { key: 'Dhuhr', label: 'الظهر', icon: 'ti-sun-high' },
  { key: 'Asr', label: 'العصر', icon: 'ti-sunset' },
  { key: 'Maghrib', label: 'المغرب', icon: 'ti-sunset-2' },
  { key: 'Isha', label: 'العشاء', icon: 'ti-moon' }
]

function cleanTime(str) {
  return String(str || '').split(' ')[0]
}
function formatTime12(hhmm) {
  const [h, m] = cleanTime(hhmm).split(':').map(Number)
  if (isNaN(h)) return hhmm
  const period = h >= 12 ? 'م' : 'ص'
  const h12 = h % 12 || 12
  return `${toArDigits(h12)}:${toArDigits(String(m).padStart(2, '0'))} ${period}`
}

async function fetchPrayerTimes(coords = null) {
  prayerLoading.value = true
  prayerError.value = ''
  try {
    let url
    if (coords) {
      url = `https://api.aladhan.com/v1/timings?latitude=${coords.latitude}&longitude=${coords.longitude}&method=5`
    } else {
      const c = PRAYER_CITIES[selectedCityIdx.value] || PRAYER_CITIES[0]
      url = `https://api.aladhan.com/v1/timingsByCity?city=${encodeURIComponent(c.city)}&country=${encodeURIComponent(c.country)}&method=${c.method}`
      gpsLabel.value = ''
    }
    const res = await fetch(url)
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()
    timings.value = json.data.timings
    const h = json.data.date?.hijri
    if (h) hijriDate.value = `${toArDigits(h.day)} ${h.month?.ar || ''} ${toArDigits(h.year)} هـ`
  } catch {
    prayerError.value = 'تعذّر تحميل مواقيت الصلاة حاليًا. تأكد من الاتصال بالإنترنت وحاول مرة أخرى.'
  } finally {
    prayerLoading.value = false
  }
}

function onCityChange() {
  localStorage.setItem(PRAYER_KEY, String(selectedCityIdx.value))
  fetchPrayerTimes()
}

function useGpsLocation() {
  if (!navigator.geolocation) {
    prayerError.value = 'المتصفح لا يدعم تحديد الموقع تلقائيًا.'
    return
  }
  prayerLoading.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      gpsLabel.value = 'حسب موقعك الحالي (GPS)'
      fetchPrayerTimes(pos.coords)
    },
    () => {
      prayerLoading.value = false
      prayerError.value = 'لم يتم السماح بالوصول للموقع — يمكنك اختيار مدينتك من القائمة.'
    }
  )
}

const nextPrayer = computed(() => {
  if (!timings.value) return null
  const now = new Date(nowTick.value)
  const order = ['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha']
  for (const key of order) {
    const [h, m] = cleanTime(timings.value[key]).split(':').map(Number)
    const dt = new Date(now)
    dt.setHours(h, m, 0, 0)
    if (dt > now) {
      const diff = Math.floor((dt - now) / 1000)
      const hrs = Math.floor(diff / 3600)
      const mins = Math.floor((diff % 3600) / 60)
      const secs = diff % 60
      return {
        key,
        label: PRAYER_NAMES.find((p) => p.key === key)?.label || key,
        time: formatTime12(timings.value[key]),
        countdown: `${toArDigits(String(hrs).padStart(2, '0'))}:${toArDigits(String(mins).padStart(2, '0'))}:${toArDigits(String(secs).padStart(2, '0'))}`
      }
    }
  }
  // بعد العشاء -> فجر الغد
  const [fh, fm] = cleanTime(timings.value.Fajr).split(':').map(Number)
  const tomorrowFajr = new Date(now)
  tomorrowFajr.setDate(tomorrowFajr.getDate() + 1)
  tomorrowFajr.setHours(fh, fm, 0, 0)
  const diff = Math.floor((tomorrowFajr - now) / 1000)
  const hrs = Math.floor(diff / 3600)
  const mins = Math.floor((diff % 3600) / 60)
  const secs = diff % 60
  return {
    key: 'Fajr',
    label: 'الفجر',
    time: formatTime12(timings.value.Fajr),
    countdown: `${toArDigits(String(hrs).padStart(2, '0'))}:${toArDigits(String(mins).padStart(2, '0'))}:${toArDigits(String(secs).padStart(2, '0'))}`
  }
})

/* ==================== 3. كم باقي على رمضان ==================== */
function getHijriParts(date) {
  const fmt = new Intl.DateTimeFormat('en-u-ca-islamic-umalqura', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric'
  })
  const parts = fmt.formatToParts(date)
  const get = (t) => Number(parts.find((p) => p.type === t)?.value || 0)
  return { day: get('day'), month: get('month'), year: get('year') }
}

function findNextRamadanStart(fromDate) {
  const cur = getHijriParts(fromDate)
  if (cur.month === 9) {
    return { inRamadan: true, ramadanDay: cur.day, hijriYear: cur.year, startDate: null }
  }
  const targetYear = cur.month < 9 ? cur.year : cur.year + 1
  const probe = new Date(fromDate)
  probe.setHours(0, 0, 0, 0)
  for (let i = 0; i <= 370; i++) {
    const p = getHijriParts(probe)
    if (p.year === targetYear && p.month === 9 && p.day === 1) {
      return { inRamadan: false, hijriYear: targetYear, startDate: new Date(probe) }
    }
    probe.setDate(probe.getDate() + 1)
  }
  return { inRamadan: false, hijriYear: targetYear, startDate: null }
}

const ramadanInfo = computed(() => {
  const now = new Date(nowTick.value)
  const info = findNextRamadanStart(now)
  if (info.inRamadan) return info
  if (!info.startDate) return { ...info, days: 0, hours: 0, minutes: 0, seconds: 0 }
  const diff = Math.max(0, Math.floor((info.startDate - now) / 1000))
  return {
    ...info,
    days: Math.floor(diff / 86400),
    hours: Math.floor((diff % 86400) / 3600),
    minutes: Math.floor((diff % 3600) / 60),
    seconds: diff % 60,
    gregorianLabel: info.startDate.toLocaleDateString('ar-EG', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    })
  }
})

/* ==================== 4. أذكار الصباح والمساء ==================== */
const azkarPeriod = ref(new Date().getHours() < 15 ? 'morning' : 'evening')
const azkarRemaining = ref({})

const currentAzkar = computed(() => (azkarPeriod.value === 'morning' ? AZKAR_MORNING : AZKAR_EVENING))
function getRem(item) {
  return azkarRemaining.value[item.id] ?? item.count
}
function tapZikr(item) {
  const rem = getRem(item)
  if (rem <= 0) return
  azkarRemaining.value = { ...azkarRemaining.value, [item.id]: rem - 1 }
  if (navigator.vibrate) navigator.vibrate(12)
}
function resetAzkar() {
  const copy = { ...azkarRemaining.value }
  for (const item of currentAzkar.value) delete copy[item.id]
  azkarRemaining.value = copy
}
const azkarDoneCount = computed(() => currentAzkar.value.filter((i) => getRem(i) === 0).length)

/* ==================== 5. السبحة الإلكترونية ==================== */
const TASBIH_KEY = 'basaer:tasbih'
function loadTasbih() {
  try {
    return JSON.parse(localStorage.getItem(TASBIH_KEY)) || { count: 0, total: 0, phraseIdx: 0, target: 33 }
  } catch {
    return { count: 0, total: 0, phraseIdx: 0, target: 33 }
  }
}
const tasbih = ref(loadTasbih())
watch(tasbih, (v) => {
  try { localStorage.setItem(TASBIH_KEY, JSON.stringify(v)) } catch {}
}, { deep: true })

function tapTasbih() {
  tasbih.value.count += 1
  tasbih.value.total += 1
  if (navigator.vibrate) navigator.vibrate(15)
  if (tasbih.value.target > 0 && tasbih.value.count >= tasbih.value.target) {
    if (navigator.vibrate) navigator.vibrate([25, 40, 25])
  }
}
function resetTasbihCount() {
  tasbih.value.count = 0
}
function resetTasbihAll() {
  tasbih.value.count = 0
  tasbih.value.total = 0
}

/* ==================== 6. حاسبة الزكاة ==================== */
const zakat = ref({
  currency: 'ج.م',
  gold24Price: 4600,
  cash: 0,
  gold24: 0,
  gold21: 0,
  gold18: 0,
  tradeGoods: 0,
  debts: 0
})

const zakatCalc = computed(() => {
  const g24 = Math.max(0, Number(zakat.value.gold24Price) || 0)
  const nisab = g24 * 85
  const goldValue =
    (Number(zakat.value.gold24) || 0) * g24 +
    (Number(zakat.value.gold21) || 0) * (g24 * (21 / 24)) +
    (Number(zakat.value.gold18) || 0) * (g24 * (18 / 24))
  const gross = (Number(zakat.value.cash) || 0) + goldValue + (Number(zakat.value.tradeGoods) || 0)
  const net = Math.max(0, gross - (Number(zakat.value.debts) || 0))
  const eligible = nisab > 0 && net >= nisab
  const due = eligible ? net * 0.025 : 0
  return { nisab, goldValue, net, eligible, due }
})

function fmtMoney(n) {
  return toArDigits(Math.round(n).toLocaleString('en-US')) + ' ' + zakat.value.currency
}

let timer = null
onMounted(() => {
  fetchPrayerTimes()
  timer = setInterval(() => (nowTick.value = Date.now()), 1000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <div class="tools-page">
    <header class="head">
      <h1>أدوات المسلم وإذاعة القرآن</h1>
      <p>إذاعة القرآن الكريم المباشرة، ومواقيت الصلاة، وعداد رمضان، والأذكار، والسبحة، وحاسبة الزكاة في مكان واحد هادئ.</p>
    </header>

    <nav class="tabs" role="tablist" aria-label="الأدوات">
      <button
        v-for="t in TABS"
        :key="t.id"
        type="button"
        role="tab"
        :aria-selected="activeTab === t.id"
        class="tab"
        :class="{ active: activeTab === t.id }"
        @click="selectTab(t.id)"
      >
        <i class="ti" :class="t.icon" aria-hidden="true"></i>
        <span>{{ t.label }}</span>
      </button>
    </nav>

    <!-- 1. إذاعة القرآن الكريم -->
    <section v-if="activeTab === 'radio'" class="panel">
      <div class="panel-note">
        <i class="ti ti-broadcast" aria-hidden="true"></i>
        <span>تعمل الإذاعة في مشغّل الموقع السفلي، ويمكنك تصفح الدروس والصوتيات بحرية بينما الإذاعة شغّالة في الخلفية.</span>
      </div>
      <div class="radio-grid">
        <button
          v-for="st in RADIO_STATIONS"
          :key="st.id"
          type="button"
          class="radio-card"
          :class="{ playing: player.isCurrent(st.id) && player.playing, featured: st.featured }"
          @click="playStation(st)"
        >
          <span class="r-icon" aria-hidden="true">
            <i class="ti" :class="player.isCurrent(st.id) && player.playing ? 'ti-player-pause' : 'ti-player-play'"></i>
          </span>
          <span class="r-info">
            <strong>{{ st.title }}</strong>
            <small>{{ st.subtitle }}</small>
          </span>
          <span v-if="player.isCurrent(st.id) && player.playing" class="live-pill">مباشر</span>
        </button>
      </div>
    </section>

    <!-- 2. مواقيت الصلاة -->
    <section v-else-if="activeTab === 'prayer'" class="panel">
      <div class="prayer-bar">
        <select v-model="selectedCityIdx" class="input" aria-label="اختر المدينة" @change="onCityChange">
          <option v-for="(c, idx) in PRAYER_CITIES" :key="c.city" :value="idx">{{ c.label }}</option>
        </select>
        <button type="button" class="btn-outline" @click="useGpsLocation">
          <i class="ti ti-current-location" aria-hidden="true"></i>
          موقعي الحالي
        </button>
        <span v-if="hijriDate" class="hijri-pill">{{ hijriDate }}</span>
      </div>

      <p v-if="gpsLabel" class="muted-note">{{ gpsLabel }}</p>
      <p v-if="prayerError" class="err-box">{{ prayerError }}</p>
      <p v-else-if="prayerLoading" class="muted-note">جاري تحميل مواقيت الصلاة...</p>

      <template v-else-if="timings">
        <div v-if="nextPrayer" class="hero-card">
          <span class="hero-lbl">الصلاة القادمة: {{ nextPrayer.label }} ({{ nextPrayer.time }})</span>
          <div class="hero-big">{{ nextPrayer.countdown }}</div>
          <span class="hero-sub">الوقت المتبقي على أذان {{ nextPrayer.label }}</span>
        </div>

        <div class="prayer-grid">
          <div
            v-for="p in PRAYER_NAMES"
            :key="p.key"
            class="prayer-card"
            :class="{ next: nextPrayer?.key === p.key }"
          >
            <i class="ti" :class="p.icon" aria-hidden="true"></i>
            <span class="p-name">{{ p.label }}</span>
            <strong class="p-time">{{ formatTime12(timings[p.key]) }}</strong>
          </div>
        </div>
      </template>
    </section>

    <!-- 3. كم باقي على رمضان -->
    <section v-else-if="activeTab === 'ramadan'" class="panel">
      <div class="hero-card ramadan-hero">
        <i class="ti ti-moon-stars ramadan-icon" aria-hidden="true"></i>
        <template v-if="ramadanInfo.inRamadan">
          <span class="hero-lbl">مبارك عليكم الشهر الكريم</span>
          <div class="hero-big">اليوم {{ toArDigits(ramadanInfo.ramadanDay) }} من رمضان {{ toArDigits(ramadanInfo.hijriYear) }} هـ</div>
          <span class="hero-sub">تقبّل الله منّا ومنكم الصيام والقيام وصالح الأعمال</span>
        </template>
        <template v-else>
          <span class="hero-lbl">العدّ التنازلي لشهر رمضان المبارك {{ toArDigits(ramadanInfo.hijriYear) }} هـ</span>
          <div class="countdown-boxes">
            <div class="cd-box"><strong>{{ toArDigits(ramadanInfo.days) }}</strong><span>يوم</span></div>
            <div class="cd-box"><strong>{{ toArDigits(ramadanInfo.hours) }}</strong><span>ساعة</span></div>
            <div class="cd-box"><strong>{{ toArDigits(ramadanInfo.minutes) }}</strong><span>دقيقة</span></div>
            <div class="cd-box"><strong>{{ toArDigits(ramadanInfo.seconds) }}</strong><span>ثانية</span></div>
          </div>
          <span v-if="ramadanInfo.gregorianLabel" class="hero-sub">
            يوافق تقريبًا {{ ramadanInfo.gregorianLabel }} (بحسب تقويم أم القرى، وقد يختلف يومًا بحسب الرؤية الشرعية)
          </span>
        </template>
      </div>
    </section>

    <!-- 4. أذكار الصباح والمساء -->
    <section v-else-if="activeTab === 'azkar'" class="panel">
      <div class="azkar-bar">
        <div class="seg">
          <button type="button" :class="{ active: azkarPeriod === 'morning' }" @click="azkarPeriod = 'morning'">
            <i class="ti ti-sunrise"></i> أذكار الصباح
          </button>
          <button type="button" :class="{ active: azkarPeriod === 'evening' }" @click="azkarPeriod = 'evening'">
            <i class="ti ti-moon"></i> أذكار المساء
          </button>
        </div>
        <div class="azkar-meta">
          <span>أنجزت {{ toArDigits(azkarDoneCount) }} من {{ toArDigits(currentAzkar.length) }}</span>
          <button type="button" class="btn-outline sm" @click="resetAzkar">إعادة الضبط</button>
        </div>
      </div>

      <div class="azkar-list">
        <button
          v-for="item in currentAzkar"
          :key="item.id"
          type="button"
          class="zikr-card"
          :class="{ done: getRem(item) === 0 }"
          @click="tapZikr(item)"
        >
          <p class="zikr-text">{{ item.text }}</p>
          <div class="zikr-foot">
            <small>{{ item.note }}</small>
            <span class="zikr-count">
              <template v-if="getRem(item) === 0"><i class="ti ti-check"></i> تم</template>
              <template v-else>{{ toArDigits(getRem(item)) }} / {{ toArDigits(item.count) }}</template>
            </span>
          </div>
        </button>
      </div>
    </section>

    <!-- 5. السبحة الإلكترونية -->
    <section v-else-if="activeTab === 'tasbih'" class="panel tasbih-panel">
      <div class="tasbih-controls">
        <select v-model.number="tasbih.phraseIdx" class="input" aria-label="اختر الذكر">
          <option v-for="(ph, idx) in TASBIH_PHRASES" :key="idx" :value="idx">{{ ph }}</option>
        </select>
        <div class="seg">
          <button type="button" :class="{ active: tasbih.target === 33 }" @click="tasbih.target = 33">٣٣</button>
          <button type="button" :class="{ active: tasbih.target === 100 }" @click="tasbih.target = 100">١٠٠</button>
          <button type="button" :class="{ active: tasbih.target === 0 }" @click="tasbih.target = 0">مفتوح</button>
        </div>
      </div>

      <div class="tasbih-phrase">{{ TASBIH_PHRASES[tasbih.phraseIdx] }}</div>

      <button type="button" class="tasbih-btn" @click="tapTasbih" aria-label="تسبيح">
        <span class="t-num">{{ toArDigits(tasbih.count) }}</span>
        <span class="t-sub">{{ tasbih.target ? `من ${toArDigits(tasbih.target)}` : 'اضغط للتسبيح' }}</span>
      </button>

      <div class="tasbih-foot">
        <span>الإجمالي المحفوظ: <strong>{{ toArDigits(tasbih.total) }}</strong></span>
        <div style="display:flex;gap:8px">
          <button type="button" class="btn-outline sm" @click="resetTasbihCount">تصفير الجلسة</button>
          <button type="button" class="btn-outline sm" @click="resetTasbihAll">تصفير الكل</button>
        </div>
      </div>
    </section>

    <!-- 6. حاسبة الزكاة -->
    <section v-else-if="activeTab === 'zakat'" class="panel">
      <div class="zakat-grid">
        <div class="zakat-form">
          <div class="z-row">
            <label>العملة
              <select v-model="zakat.currency" class="input">
                <option value="ج.م">جنيه مصري (ج.م)</option>
                <option value="ر.س">ريال سعودي (ر.س)</option>
                <option value="د.إ">درهم إماراتي (د.إ)</option>
                <option value="$">دولار ($)</option>
              </select>
            </label>
            <label>سعر جرام الذهب عيار ٢٤ اليوم
              <input v-model.number="zakat.gold24Price" type="number" min="0" step="10" class="input">
            </label>
          </div>
          <label>النقد والمدخرات البنكية ({{ zakat.currency }})
            <input v-model.number="zakat.cash" type="number" min="0" class="input">
          </label>
          <div class="z-row three">
            <label>ذهب عيار ٢٤ (جرام)
              <input v-model.number="zakat.gold24" type="number" min="0" step="0.5" class="input">
            </label>
            <label>ذهب عيار ٢١ (جرام)
              <input v-model.number="zakat.gold21" type="number" min="0" step="0.5" class="input">
            </label>
            <label>ذهب عيار ١٨ (جرام)
              <input v-model.number="zakat.gold18" type="number" min="0" step="0.5" class="input">
            </label>
          </div>
          <div class="z-row">
            <label>عروض التجارة والأسهم ({{ zakat.currency }})
              <input v-model.number="zakat.tradeGoods" type="number" min="0" class="input">
            </label>
            <label>الديون المستحقة عليك (تُخصم)
              <input v-model.number="zakat.debts" type="number" min="0" class="input">
            </label>
          </div>
        </div>

        <div class="zakat-result">
          <div class="zr-row"><span>قيمة النصاب (٨٥ جرام ذهب عيار ٢٤)</span><strong>{{ fmtMoney(zakatCalc.nisab) }}</strong></div>
          <div class="zr-row"><span>صافي المال الخاضع للزكاة</span><strong>{{ fmtMoney(zakatCalc.net) }}</strong></div>
          <div class="zr-verdict" :class="{ ok: zakatCalc.eligible }">
            <template v-if="zakatCalc.eligible">
              <span>تجب الزكاة (٢.٥٪) بعد مرور الحول:</span>
              <strong>{{ fmtMoney(zakatCalc.due) }}</strong>
            </template>
            <template v-else>
              <span>لم يبلغ المال النصاب بعد — لا زكاة واجبة.</span>
            </template>
          </div>
          <small class="muted-note">ملاحظة: النصاب محسوب على ٨٥ جرام ذهب خالص عيار ٢٤، ويُشترط مرور حول هجري كامل.</small>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 20px; }
.head h1 { margin: 0 0 6px; font-size: 24px; font-weight: 700; }
.head p { margin: 0; color: var(--text-2); font-size: 15px; }

.tabs { display: flex; flex-wrap: wrap; gap: 8px; }
.tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; border-radius: 999px;
  background: var(--surface); border: 1px solid var(--border);
  color: var(--text-2); font-size: 14px; font-weight: 500;
  transition: .15s var(--ease);
}
.tab:hover { background: var(--surface-2); color: var(--text); }
.tab.active { background: var(--primary); border-color: var(--primary); color: var(--on-primary); }
.tab .ti { font-size: 18px; }

.panel { display: flex; flex-direction: column; gap: 16px; }
.panel-note {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 14px; border-radius: var(--radius);
  background: var(--primary-soft); color: var(--primary); font-size: 14px;
}
.panel-note .ti { font-size: 20px; flex-shrink: 0; }

/* Radio */
.radio-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.radio-card {
  display: flex; align-items: center; gap: 12px; text-align: right;
  padding: 14px; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border);
  transition: .15s var(--ease);
}
.radio-card:hover { border-color: var(--primary); background: var(--surface-2); }
.radio-card.featured { border-color: var(--primary); background: var(--primary-soft); }
.radio-card.playing { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary-soft); }
.r-icon {
  width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
  display: grid; place-items: center; font-size: 20px;
  background: var(--primary); color: var(--on-primary);
}
.r-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.r-info strong { font-size: 15px; font-weight: 600; color: var(--text); }
.r-info small { font-size: 12.5px; color: var(--text-3); }
.live-pill { font-size: 11px; font-weight: 700; background: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 999px; }

/* Prayer */
.prayer-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.input {
  padding: 9px 12px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong); background: var(--bg); color: var(--text);
  font-family: inherit; font-size: 14px;
}
.btn-outline {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 14px; border-radius: var(--radius-sm);
  border: 1px solid var(--border-strong); background: var(--surface); color: var(--text);
  font-size: 14px; transition: .15s var(--ease);
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); }
.btn-outline.sm { padding: 6px 12px; font-size: 13px; }
.hijri-pill { margin-inline-start: auto; font-size: 14px; font-weight: 500; color: var(--primary); background: var(--primary-soft); padding: 6px 12px; border-radius: 999px; }
.muted-note { margin: 0; font-size: 13px; color: var(--text-3); }
.err-box { margin: 0; padding: 12px; border-radius: var(--radius-sm); background: #fee2e2; color: #991b1b; font-size: 14px; }

.hero-card {
  padding: 26px 20px; border-radius: var(--radius); text-align: center;
  background: linear-gradient(135deg, var(--primary-soft), var(--surface));
  border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.hero-lbl { font-size: 15px; font-weight: 500; color: var(--primary); }
.hero-big { font-size: 38px; font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; line-height: 1.2; }
.hero-sub { font-size: 13.5px; color: var(--text-2); }

.prayer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
.prayer-card {
  padding: 16px 12px; border-radius: var(--radius); text-align: center;
  background: var(--surface); border: 1px solid var(--border);
  display: flex; flex-direction: column; align-items: center; gap: 6px;
}
.prayer-card .ti { font-size: 22px; color: var(--text-3); }
.prayer-card .p-name { font-size: 14px; color: var(--text-2); }
.prayer-card .p-time { font-size: 16px; color: var(--text); }
.prayer-card.next { border-color: var(--primary); background: var(--primary-soft); }
.prayer-card.next .ti, .prayer-card.next .p-name { color: var(--primary); font-weight: 600; }

/* Ramadan */
.ramadan-hero { padding: 34px 20px; gap: 14px; }
.ramadan-icon { font-size: 44px; color: var(--primary); }
.countdown-boxes { display: grid; grid-template-columns: repeat(4, minmax(72px, 110px)); gap: 10px; margin: 6px 0; }
.cd-box {
  background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
  padding: 14px 8px; display: flex; flex-direction: column; align-items: center;
}
.cd-box strong { font-size: 30px; font-weight: 700; color: var(--primary); line-height: 1.1; }
.cd-box span { font-size: 13px; color: var(--text-2); margin-top: 4px; }

/* Azkar */
.azkar-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.seg { display: inline-flex; background: var(--surface); border: 1px solid var(--border); padding: 3px; border-radius: 10px; gap: 4px; }
.seg button { padding: 6px 14px; border-radius: 8px; font-size: 14px; color: var(--text-2); display: inline-flex; align-items: center; gap: 5px; }
.seg button.active { background: var(--primary); color: var(--on-primary); font-weight: 500; }
.azkar-meta { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--text-2); }
.azkar-list { display: flex; flex-direction: column; gap: 10px; }
.zikr-card {
  width: 100%; text-align: right; padding: 18px; border-radius: var(--radius);
  background: var(--surface); border: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 12px; transition: .15s var(--ease);
}
.zikr-card:hover { border-color: var(--primary); }
.zikr-card:active { transform: scale(0.995); }
.zikr-card.done { opacity: .62; border-color: var(--primary); background: var(--primary-soft); }
.zikr-text { margin: 0; font-family: var(--font-quote); font-size: 21px; line-height: 2; color: var(--text); }
.zikr-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.zikr-foot small { color: var(--text-3); font-size: 13px; }
.zikr-count {
  padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 600;
  background: var(--primary); color: var(--on-primary); display: inline-flex; align-items: center; gap: 4px;
}

/* Tasbih */
.tasbih-panel { align-items: center; text-align: center; padding: 12px 0; }
.tasbih-controls { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; width: 100%; }
.tasbih-phrase { font-family: var(--font-quote); font-size: 26px; color: var(--primary); margin: 8px 0; }
.tasbih-btn {
  width: 210px; height: 210px; border-radius: 50%;
  background: radial-gradient(circle at 35% 30%, var(--primary), var(--primary-hover));
  color: var(--on-primary); box-shadow: 0 14px 36px rgba(47, 107, 90, 0.25);
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
  transition: transform .08s ease; user-select: none;
}
.tasbih-btn:active { transform: scale(0.95); }
.t-num { font-size: 54px; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.t-sub { font-size: 14px; opacity: .88; }
.tasbih-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 16px; margin-top: 8px; color: var(--text-2); font-size: 14px; }

/* Zakat */
.zakat-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 18px; align-items: start; }
.zakat-form { display: flex; flex-direction: column; gap: 12px; background: var(--surface); border: 1px solid var(--border); padding: 18px; border-radius: var(--radius); }
.zakat-form label { display: flex; flex-direction: column; gap: 5px; font-size: 13.5px; color: var(--text-2); }
.z-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.z-row.three { grid-template-columns: repeat(3, 1fr); }
.zakat-result { display: flex; flex-direction: column; gap: 12px; background: var(--surface); border: 1px solid var(--border); padding: 18px; border-radius: var(--radius); }
.zr-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 14px; color: var(--text-2); padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.zr-row strong { color: var(--text); font-size: 16px; }
.zr-verdict {
  padding: 16px; border-radius: var(--radius-sm); text-align: center;
  background: var(--surface-2); color: var(--text-2);
  display: flex; flex-direction: column; gap: 6px;
}
.zr-verdict.ok { background: var(--primary-soft); color: var(--primary); }
.zr-verdict strong { font-size: 26px; font-weight: 700; }

@media (max-width: 820px) {
  .zakat-grid { grid-template-columns: 1fr; }
  .z-row, .z-row.three { grid-template-columns: 1fr; }
  .countdown-boxes { grid-template-columns: repeat(4, 1fr); width: 100%; }
}
</style>
