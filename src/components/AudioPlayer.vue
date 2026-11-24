<script setup>
import { computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { usePlayerStore } from '@/stores/player'
import { clock, toArDigits } from '@/utils/format'
import { useSiteStore } from '@/stores/site'

const p = usePlayerStore()
const pct = computed(() => (p.duration ? (p.position / p.duration) * 100 : 0))
const store = useSiteStore()
const sub = computed(() => p.track?.series?.title || store.site.speaker || store.site.name)
const rateLabel = computed(() => `${toArDigits(p.rate)}×`)

function onSeek(e) {
  p.seek(Number(e.target.value))
}

function close() {
  p.pause()
  p.track = null
}

// مسافة = تشغيل/إيقاف، الأسهم = تقديم/ترجيع (خارج حقول الكتابة)
function onKey(e) {
  if (!p.track) return
  const tag = e.target.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.isContentEditable) return
  if (e.code === 'Space' && tag !== 'BUTTON' && tag !== 'A') {
    e.preventDefault()
    p.toggle()
  }
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))

// Media Session: التحكم من شاشة القفل وأزرار الوسائط
watch(
  () => p.track,
  (t) => {
    if (!t || !('mediaSession' in navigator)) return
    navigator.mediaSession.metadata = new MediaMetadata({ title: t.title, artist: sub.value, album: store.site.name })
    const ms = navigator.mediaSession
    ms.setActionHandler('play', p.resume)
    ms.setActionHandler('pause', p.pause)
    ms.setActionHandler('previoustrack', p.prev)
    ms.setActionHandler('nexttrack', p.next)
    ms.setActionHandler('seekbackward', () => p.skip(-15))
    ms.setActionHandler('seekforward', () => p.skip(15))
  }
)
</script>

<template>
  <Transition name="slide">
    <section v-if="p.track" class="player" aria-label="مشغّل الصوتيات">
      <div class="seek">
        <label for="seek" class="sr-only">موضع التشغيل</label>
        <input
          id="seek"
          type="range"
          min="0"
          :max="p.duration || 0"
          step="1"
          :value="p.position"
          :style="{ '--pct': pct + '%' }"
          :aria-valuetext="`${clock(p.position)} من ${clock(p.duration)}`"
          @input="onSeek"
        />
      </div>

      <div class="row">
        <RouterLink :to="`/audio/${p.track.id}`" class="now">
          <span class="art" :class="{ on: p.playing }" aria-hidden="true">
            <i class="ti ti-headphones"></i>
          </span>
          <span class="txt">
            <span class="title">{{ p.track.title }}</span>
            <span class="sub">
              {{ sub }}
              <span v-if="p.isDemo" class="demo" title="لم يُضف ملف صوتي بعد، التشغيل محاكاة">تجريبي</span>
            </span>
          </span>
        </RouterLink>

        <div class="controls">
          <button class="ctl hide-sm" aria-label="السابق" @click="p.prev"><i class="ti ti-player-skip-forward" aria-hidden="true"></i></button>
          <button class="ctl hide-sm" aria-label="ترجيع ١٥ ثانية" @click="p.skip(-15)"><i class="ti ti-rewind-forward-15" aria-hidden="true"></i></button>
          <button class="main" :aria-label="p.playing ? 'إيقاف مؤقت' : 'تشغيل'" @click="p.toggle">
            <i class="ti" :class="p.playing ? 'ti-player-pause' : 'ti-player-play'" aria-hidden="true"></i>
          </button>
          <button class="ctl hide-sm" aria-label="تقديم ١٥ ثانية" @click="p.skip(15)"><i class="ti ti-rewind-backward-15" aria-hidden="true"></i></button>
          <button class="ctl hide-sm" aria-label="التالي" @click="p.next"><i class="ti ti-player-skip-back" aria-hidden="true"></i></button>
        </div>

        <div class="extra">
          <span class="time">{{ clock(p.position) }} / {{ clock(p.duration) }}</span>
          <button class="rate" :aria-label="`سرعة التشغيل ${rateLabel}`" @click="p.cycleRate">{{ rateLabel }}</button>
          <button class="ctl" aria-label="إغلاق المشغّل" @click="close"><i class="ti ti-x" aria-hidden="true"></i></button>
        </div>
      </div>
    </section>
  </Transition>
</template>

<style scoped>
.player {
  position: fixed;
  bottom: 0;
  inset-inline-start: var(--sidebar-w);
  inset-inline-end: 0;
  height: var(--player-h);
  background: var(--bg);
  border-top: 1px solid var(--border);
  z-index: 25;
  display: flex;
  flex-direction: column;
}
.seek { height: 0; position: relative; }
.seek input {
  position: absolute;
  top: -7px;
  inset-inline: 0;
  width: 100%;
  height: 14px;
  margin: 0;
  background: transparent;
  -webkit-appearance: none;
  appearance: none;
  cursor: pointer;
}
.seek input::-webkit-slider-runnable-track {
  height: 3px;
  background: linear-gradient(to left, var(--primary) var(--pct), var(--border) var(--pct));
}
.seek input::-moz-range-track {
  height: 3px;
  background: linear-gradient(to left, var(--primary) var(--pct), var(--border) var(--pct));
}
.seek input::-webkit-slider-thumb {
  -webkit-appearance: none;
  width: 12px; height: 12px; margin-top: -4.5px;
  border-radius: 50%;
  background: var(--primary);
  opacity: 0;
  transition: opacity 0.15s;
}
.seek input::-moz-range-thumb { width: 12px; height: 12px; border: 0; border-radius: 50%; background: var(--primary); }
.seek input:hover::-webkit-slider-thumb, .seek input:focus-visible::-webkit-slider-thumb { opacity: 1; }

.row {
  flex: 1;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 16px;
  padding: 0 20px;
}
.now { display: flex; align-items: center; gap: 12px; min-width: 0; }
.art {
  width: 44px; height: 44px; border-radius: 10px;
  display: grid; place-items: center;
  background: var(--audio); color: var(--audio-text);
  font-size: 22px; flex-shrink: 0;
  transition: background 0.2s var(--ease), color 0.2s var(--ease);
}
.art.on { background: var(--primary); color: var(--on-primary); }
.txt { display: flex; flex-direction: column; min-width: 0; line-height: 1.4; }
.title { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.now:hover .title { color: var(--primary); }
.sub { font-size: 12px; color: var(--text-3); display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; }
.demo { background: var(--sand); color: var(--sand-text); font-size: 11px; padding: 0 6px; border-radius: 4px; }

.controls { display: flex; align-items: center; gap: 4px; }
.ctl {
  width: 36px; height: 36px; border-radius: 50%;
  display: grid; place-items: center;
  font-size: 20px; color: var(--text-2);
}
.ctl:hover { background: var(--surface); color: var(--text); }
.main {
  width: 44px; height: 44px; border-radius: 50%;
  display: grid; place-items: center;
  background: var(--primary); color: var(--on-primary);
  font-size: 22px; margin: 0 4px;
  transition: background 0.15s var(--ease), transform 0.15s var(--ease);
}
.main:hover { background: var(--primary-hover); }
.main:active { transform: scale(0.95); }

.extra { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.time { font-size: 12px; color: var(--text-3); font-variant-numeric: tabular-nums; white-space: nowrap; }
.rate {
  height: 28px; min-width: 44px; padding: 0 8px;
  border-radius: 999px; font-size: 12px; font-weight: 500;
  background: var(--surface); color: var(--text-2);
}
.rate:hover { background: var(--surface-2); color: var(--text); }

.slide-enter-active, .slide-leave-active { transition: transform 0.25s var(--ease); }
.slide-enter-from, .slide-leave-to { transform: translateY(100%); }

@media (max-width: 900px) {
  .player { inset-inline-start: 0; bottom: var(--mobile-nav-h); }
  .row { grid-template-columns: minmax(0, 1fr) auto auto; gap: 8px; padding: 0 12px; }
  .hide-sm, .time { display: none; }
  .main { margin: 0; }
}
</style>
