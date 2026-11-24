const arNum = new Intl.NumberFormat('ar-EG')
const arDate = new Intl.DateTimeFormat('ar-EG', { day: 'numeric', month: 'long', year: 'numeric' })

export const num = (n) => arNum.format(n)

export const compactNum = (n) =>
  new Intl.NumberFormat('ar-EG', { notation: 'compact', maximumFractionDigits: 1 }).format(n)

export const date = (iso) => arDate.format(new Date(iso))

export function relDate(iso) {
  const days = Math.round((Date.now() - new Date(iso).getTime()) / 86400000)
  if (days <= 0) return 'اليوم'
  if (days === 1) return 'أمس'
  if (days < 30) return new Intl.RelativeTimeFormat('ar-EG', { numeric: 'auto' }).format(-days, 'day')
  return date(iso)
}

// seconds -> "٢٥:٢٠" or "١:٠٢:٠٥"
export function clock(sec = 0) {
  sec = Math.max(0, Math.floor(sec))
  const h = Math.floor(sec / 3600)
  const m = Math.floor((sec % 3600) / 60)
  const s = sec % 60
  const pad = (x) => String(x).padStart(2, '0')
  const raw = h ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`
  return raw.replace(/\d/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d])
}

export const toArDigits = (s) => String(s).replace(/\d/g, (d) => '٠١٢٣٤٥٦٧٨٩'[d])
