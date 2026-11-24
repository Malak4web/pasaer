// لوحة تحكم بصائر — سلوكيات بسيطة بدون مكتبات
(() => {
  const $ = (s, el = document) => el.querySelector(s)
  const $$ = (s, el = document) => [...el.querySelectorAll(s)]

  // قائمة الموبايل
  $$('[data-toggle-nav]').forEach((b) => b.addEventListener('click', () => document.body.classList.toggle('nav-open')))

  // تأكيد قبل الحذف
  $$('form[data-confirm]').forEach((f) =>
    f.addEventListener('submit', (e) => {
      if (!confirm(f.dataset.confirm)) e.preventDefault()
    })
  )

  // إخفاء رسالة النجاح تلقائيًا
  const toast = $('.toast')
  if (toast) setTimeout(() => toast.remove(), 3500)

  // تنبيه لو فيه تعديلات مش محفوظة
  const form = $('form[data-dirty-check]')
  if (form) {
    let dirty = false
    form.addEventListener('input', () => (dirty = true))
    form.addEventListener('submit', () => (dirty = false))
    window.addEventListener('beforeunload', (e) => {
      if (dirty) e.preventDefault()
    })
  }

  // Ctrl/Cmd + S للحفظ
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's' && form) {
      e.preventDefault()
      form.requestSubmit()
    }
  })

  // ---------- يوتيوب: معاينة من الرابط ----------
  const ytInput = $('#youtube_url')
  const ytPreview = $('#yt-preview')
  const ytId = (v) => {
    v = (v || '').trim()
    if (/^[\w-]{11}$/.test(v)) return v
    const m = v.match(/(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/|v\/)|youtu\.be\/)([\w-]{11})/)
    return m ? m[1] : null
  }
  if (ytInput && ytPreview) {
    const update = () => {
      const id = ytId(ytInput.value)
      ytPreview.classList.toggle('on', !!id)
      if (id) {
        $('img', ytPreview).src = `https://i.ytimg.com/vi/${id}/mqdefault.jpg`
        $('span', ytPreview).textContent = `معرّف المقطع: ${id}`
      }
    }
    ytInput.addEventListener('input', update)
    update()
  }

  // ---------- الصوت: رفع بالسحب + قراءة المدة تلقائيًا ----------
  const fmt = (s) => {
    s = Math.round(s)
    const h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60
    const p = (x) => String(x).padStart(2, '0')
    return h ? `${h}:${p(m)}:${p(sec)}` : `${m}:${p(sec)}`
  }
  const readDuration = (src) => {
    const durationInput = $('#duration')
    if (!durationInput) return
    const a = new Audio()
    a.preload = 'metadata'
    a.onloadedmetadata = () => {
      if (isFinite(a.duration) && a.duration > 0) durationInput.value = fmt(a.duration)
    }
    a.src = src
  }

  const zone = $('.dropzone')
  if (zone) {
    const input = $('input[type=file]', zone)
    const label = $('[data-file-label]', zone)
    const onFile = (file) => {
      if (!file) return
      label.textContent = `${file.name} — ${(file.size / 1048576).toFixed(1)} ميجا`
      readDuration(URL.createObjectURL(file))
      const title = $('#title')
      if (title && !title.value) title.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ')
    }
    input.addEventListener('change', () => onFile(input.files[0]))
    ;['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => (e.preventDefault(), zone.classList.add('drag'))))
    ;['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => (e.preventDefault(), zone.classList.remove('drag'))))
    zone.addEventListener('drop', (e) => {
      if (e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files
        onFile(input.files[0])
      }
    })
  }

  const ext = $('#external_url')
  if (ext) ext.addEventListener('change', () => ext.value && readDuration(ext.value))

  // ---------- معاينة أيقونة الموضوع ----------
  const iconInput = $('#icon')
  const iconPreview = $('#icon-preview')
  if (iconInput && iconPreview) {
    iconInput.addEventListener('input', () => (iconPreview.className = 'ti ' + (iconInput.value || 'ti-bookmark')))
  }

  // ---------- عدّاد حروف ----------
  $$('[data-count]').forEach((el) => {
    const out = document.getElementById(el.dataset.count)
    const max = el.getAttribute('maxlength')
    const upd = () => (out.textContent = `${el.value.length} / ${max}`)
    el.addEventListener('input', upd)
    upd()
  })

  // ---------- فلاتر الجداول: إرسال تلقائي عند التغيير ----------
  $$('form.toolbar select').forEach((s) => s.addEventListener('change', () => s.form.submit()))
})()
