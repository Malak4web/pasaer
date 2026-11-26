@extends('admin.layout')
@section('title', 'تصنيف الصوتيات بالسحب والإفلات')
@section('crumb')
    <a href="{{ route('admin.audios.index') }}">الصوتيات</a>
    <i class="ti ti-chevron-left" aria-hidden="true"></i> تصنيف بالسحب والإفلات
@endsection

@section('content')
<div class="page-head">
    <div>
        <h1>تصنيف الصوتيات بالسحب والإفلات</h1>
        <p>اسحب أي صوتية (أو حدّد عدة صوتيات واسحبها دفعة واحدة) وأفلتها فوق الموضوع أو السلسلة المطلوبة — يتم الحفظ فورًا.</p>
    </div>
    <a href="{{ route('admin.audios.index') }}" class="btn btn-secondary"><i class="ti ti-arrow-right" aria-hidden="true"></i> رجوع للصوتيات</a>
</div>

<div class="org-toolbar card">
    <div class="org-tabs" role="tablist">
        <button type="button" class="org-tab active" data-mode="topic_id"><i class="ti ti-category"></i> التصنيف حسب المواضيع</button>
        <button type="button" class="org-tab" data-mode="series_id"><i class="ti ti-stack-2"></i> التصنيف حسب السلاسل</button>
    </div>
    <div class="org-filters">
        <input type="search" id="org-search" placeholder="ابحث بعنوان الصوتية..." autocomplete="off">
        <select id="org-filter-bucket" aria-label="تصفية حسب التصنيف الحالي"></select>
        <button type="button" class="btn btn-secondary btn-sm" id="org-select-visible">تحديد الظاهر</button>
        <button type="button" class="btn btn-secondary btn-sm" id="org-clear-sel" style="display:none">إلغاء التحديد (<span id="org-sel-count">0</span>)</button>
    </div>
</div>

<div class="org-grid">
    <aside class="org-buckets card" aria-label="أهداف الإفلات">
        <div class="org-head">
            <strong id="org-buckets-title">المواضيع</strong>
            <small>أفلِت الصوتيات هنا</small>
        </div>
        <div id="org-buckets-list" class="buckets-list"></div>
    </aside>

    <section class="org-items card" aria-label="قائمة الصوتيات">
        <div class="org-head">
            <strong>الصوتيات (<span id="org-visible-count">0</span>)</strong>
            <small id="org-hint">اسحب من المقبض ⋮⋮ أو من أي مكان في البطاقة</small>
        </div>
        <div id="org-items-list" class="items-list"></div>
    </section>
</div>

<div id="org-toast" class="alert alert-success toast" style="display:none" role="status"></div>

<style>
.org-toolbar { padding: 14px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
.org-tabs { display: flex; gap: 6px; background: var(--surface); padding: 4px; border-radius: 10px; }
.org-tab { border: 0; background: transparent; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-size: 14px; color: var(--text-2); display: inline-flex; align-items: center; gap: 6px; transition: .15s var(--ease); }
.org-tab.active { background: var(--primary); color: var(--on-primary); font-weight: 500; }
.org-filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; flex: 1; justify-content: flex-end; }
.org-filters input, .org-filters select { padding: 7px 12px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--card); font-size: 14px; }
.org-filters input { min-width: 220px; flex: 1; max-width: 320px; }

.org-grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 16px; align-items: start; }
.org-head { display: flex; align-items: baseline; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border); }
.org-head small { color: var(--text-3); font-size: 12px; }

.org-buckets { position: sticky; top: 72px; }
.buckets-list { padding: 10px; display: flex; flex-direction: column; gap: 6px; max-height: calc(100vh - 210px); overflow-y: auto; }
.bucket {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 12px; border-radius: 10px;
    border: 2px dashed var(--border); background: var(--surface);
    cursor: pointer; transition: .15s var(--ease); user-select: none;
}
.bucket:hover { border-color: var(--primary); background: var(--primary-soft); }
.bucket.active-filter { border-style: solid; border-color: var(--primary); background: var(--primary-soft); }
.bucket.drop-over {
    border-style: solid; border-color: var(--primary); background: var(--primary);
    color: var(--on-primary); transform: scale(1.02); box-shadow: 0 6px 18px rgba(47,107,90,.22);
}
.bucket.drop-over .b-count { background: rgba(255,255,255,.22); color: #fff; }
.b-name { font-size: 14px; font-weight: 500; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.b-count { font-size: 12px; font-weight: 600; background: var(--card); color: var(--text-2); padding: 1px 8px; border-radius: 999px; border: 1px solid var(--border); }

.items-list { padding: 10px; display: flex; flex-direction: column; gap: 6px; max-height: calc(100vh - 210px); overflow-y: auto; }
.audio-card {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 12px; border-radius: 10px;
    border: 1px solid var(--border); background: var(--card);
    cursor: grab; transition: .12s var(--ease);
}
.audio-card:active { cursor: grabbing; }
.audio-card:hover { border-color: var(--border-strong); background: var(--surface); }
.audio-card.selected { border-color: var(--primary); background: var(--primary-soft); }
.audio-card.dragging { opacity: .45; }
.drag-handle { color: var(--text-3); font-size: 18px; flex-shrink: 0; }
.audio-main { flex: 1; min-width: 0; }
.audio-title { font-size: 14px; font-weight: 500; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.audio-meta { font-size: 12px; color: var(--text-3); display: flex; flex-wrap: wrap; gap: 8px; margin-top: 2px; }
.audio-tag { background: var(--surface-2); color: var(--text-2); padding: 0 7px; border-radius: 999px; font-size: 11.5px; }
.audio-edit { color: var(--text-3); padding: 4px; border-radius: 6px; }
.audio-edit:hover { background: var(--surface-2); color: var(--primary); }

@media (max-width: 900px) {
    .org-grid { grid-template-columns: 1fr; }
    .org-buckets { position: static; }
    .buckets-list, .items-list { max-height: 45vh; }
}
</style>

<script>
(() => {
    const TOPICS = @json($topics);
    const SERIES = @json($seriesList);
    const AUDIOS = @json($audios);
    const ENDPOINT = @json(route('admin.audios.recategorize'));
    const CSRF = @json(csrf_token());

    let mode = 'topic_id'; // topic_id | series_id
    let filterBucket = 'all'; // 'all' | 'none' | id
    let search = '';
    const selected = new Set();
    let draggingIds = [];

    const topicName = id => (TOPICS.find(t => t.id === id)?.name) || 'بدون موضوع';
    const seriesName = id => (SERIES.find(s => s.id === id)?.title) || 'بدون سلسلة';

    const $bucketsList = document.getElementById('org-buckets-list');
    const $itemsList = document.getElementById('org-items-list');
    const $filterSelect = document.getElementById('org-filter-bucket');
    const $search = document.getElementById('org-search');
    const $visibleCount = document.getElementById('org-visible-count');
    const $clearSel = document.getElementById('org-clear-sel');
    const $selCount = document.getElementById('org-sel-count');
    const $bucketsTitle = document.getElementById('org-buckets-title');
    const $toast = document.getElementById('org-toast');

    let toastTimer;
    function showToast(msg, isError = false) {
        $toast.textContent = msg;
        $toast.className = 'alert toast ' + (isError ? 'alert-error' : 'alert-success');
        $toast.style.display = 'flex';
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { $toast.style.display = 'none'; }, 2800);
    }

    function getBuckets() {
        const list = mode === 'topic_id'
            ? TOPICS.map(t => ({ id: t.id, name: t.name, icon: t.icon || 'ti-folder' }))
            : SERIES.map(s => ({ id: s.id, name: s.title, icon: 'ti-stack-2' }));
        list.push({ id: null, name: mode === 'topic_id' ? 'بدون موضوع' : 'بدون سلسلة', icon: 'ti-folder-off' });
        return list;
    }

    function countFor(bucketId) {
        return AUDIOS.filter(a => (a[mode] ?? null) === bucketId).length;
    }

    function getFilteredAudios() {
        const q = search.trim().toLowerCase();
        return AUDIOS.filter(a => {
            if (filterBucket === 'none' && a[mode] !== null) return false;
            if (filterBucket !== 'all' && filterBucket !== 'none' && String(a[mode]) !== String(filterBucket)) return false;
            if (q && !a.title.toLowerCase().includes(q)) return false;
            return true;
        });
    }

    function updateSelUI() {
        $selCount.textContent = selected.size;
        $clearSel.style.display = selected.size > 0 ? 'inline-flex' : 'none';
    }

    function renderFilterOptions() {
        const buckets = getBuckets();
        const opts = [`<option value="all">الكل (${AUDIOS.length})</option>`];
        for (const b of buckets) {
            const val = b.id === null ? 'none' : String(b.id);
            opts.push(`<option value="${val}">${b.name} (${countFor(b.id)})</option>`);
        }
        $filterSelect.innerHTML = opts.join('');
        $filterSelect.value = filterBucket;
    }

    function renderBuckets() {
        $bucketsTitle.textContent = mode === 'topic_id' ? 'المواضيع' : 'السلاسل الصوتية';
        const buckets = getBuckets();
        $bucketsList.innerHTML = '';

        for (const b of buckets) {
            const el = document.createElement('div');
            const filterKey = b.id === null ? 'none' : String(b.id);
            el.className = 'bucket' + (filterBucket === filterKey ? ' active-filter' : '');
            el.innerHTML = `<i class="ti ${b.icon}"></i><span class="b-name"></span><span class="b-count">${countFor(b.id)}</span>`;
            el.querySelector('.b-name').textContent = b.name;

            el.addEventListener('click', () => {
                filterBucket = (filterBucket === filterKey) ? 'all' : filterKey;
                renderAll();
            });

            el.addEventListener('dragover', e => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                el.classList.add('drop-over');
            });
            el.addEventListener('dragleave', () => el.classList.remove('drop-over'));
            el.addEventListener('drop', async e => {
                e.preventDefault();
                el.classList.remove('drop-over');
                if (!draggingIds.length) return;
                await applyDrop(draggingIds.slice(), b.id, b.name);
            });

            $bucketsList.appendChild(el);
        }
    }

    function renderItems() {
        const list = getFilteredAudios();
        $visibleCount.textContent = list.length;
        $itemsList.innerHTML = '';

        if (!list.length) {
            $itemsList.innerHTML = '<div class="empty"><i class="ti ti-headphones-off"></i> لا توجد صوتيات مطابقة للبحث أو الفلتر.</div>';
            return;
        }

        const frag = document.createDocumentFragment();
        // عرض أول 250 عنصر للأداء السريع (والبحث يفلتر الكل فورًا)
        for (const a of list.slice(0, 250)) {
            const card = document.createElement('div');
            card.className = 'audio-card' + (selected.has(a.id) ? ' selected' : '');
            card.draggable = true;
            card.dataset.id = a.id;

            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = selected.has(a.id);
            cb.addEventListener('change', () => {
                if (cb.checked) selected.add(a.id); else selected.delete(a.id);
                card.classList.toggle('selected', cb.checked);
                updateSelUI();
            });

            const handle = document.createElement('i');
            handle.className = 'ti ti-grip-vertical drag-handle';

            const main = document.createElement('div');
            main.className = 'audio-main';
            const titleEl = document.createElement('div');
            titleEl.className = 'audio-title';
            titleEl.textContent = a.title;

            const meta = document.createElement('div');
            meta.className = 'audio-meta';
            meta.innerHTML = `
                <span class="audio-tag">${topicName(a.topic_id)}</span>
                ${a.series_id ? `<span class="audio-tag">${seriesName(a.series_id)}</span>` : ''}
                <span>${a.duration}</span>
                <span>${a.date}</span>
            `;
            main.append(titleEl, meta);

            const edit = document.createElement('a');
            edit.href = a.edit_url;
            edit.className = 'audio-edit';
            edit.title = 'تعديل التفاصيل';
            edit.innerHTML = '<i class="ti ti-edit"></i>';

            card.append(cb, handle, main, edit);

            card.addEventListener('dragstart', e => {
                draggingIds = selected.has(a.id) && selected.size > 0 ? Array.from(selected) : [a.id];
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggingIds.join(','));
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                draggingIds = [];
            });

            frag.appendChild(card);
        }

        if (list.length > 250) {
            const more = document.createElement('div');
            more.style.cssText = 'text-align:center;padding:10px;color:var(--text-3);font-size:13px';
            more.textContent = `معروض أول 250 من أصل ${list.length} — استخدم البحث أو اختر تصنيفًا من اليمين للتصفية.`;
            frag.appendChild(more);
        }

        $itemsList.appendChild(frag);
    }

    async function applyDrop(ids, targetValue, targetLabel) {
        const snapshot = ids.map(id => {
            const a = AUDIOS.find(x => x.id === id);
            return a ? { id, topic_id: a.topic_id, series_id: a.series_id } : null;
        }).filter(Boolean);

        // تحديث فوري متفائل
        const seriesObj = (mode === 'series_id' && targetValue !== null)
            ? SERIES.find(s => s.id === targetValue)
            : null;
        for (const id of ids) {
            const a = AUDIOS.find(x => x.id === id);
            if (!a) continue;
            a[mode] = targetValue;
            if (seriesObj && seriesObj.topic_id) {
                a.topic_id = seriesObj.topic_id;
            }
        }
        selected.clear();
        updateSelUI();
        renderAll();

        try {
            const res = await fetch(ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ ids, field: mode, value: targetValue }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            showToast(`تم نقل ${ids.length} صوتية إلى «${targetLabel}» بنجاح.`);
        } catch (err) {
            // استرجاع عند الفشل
            for (const s of snapshot) {
                const a = AUDIOS.find(x => x.id === s.id);
                if (a) { a.topic_id = s.topic_id; a.series_id = s.series_id; }
            }
            renderAll();
            showToast('فشل الحفظ — تأكد من الاتصال وحاول مجددًا.', true);
        }
    }

    function renderAll() {
        renderFilterOptions();
        renderBuckets();
        renderItems();
    }

    document.querySelectorAll('.org-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.org-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            mode = btn.dataset.mode;
            filterBucket = 'all';
            renderAll();
        });
    });

    $search.addEventListener('input', () => { search = $search.value; renderItems(); });
    $filterSelect.addEventListener('change', () => { filterBucket = $filterSelect.value; renderAll(); });

    document.getElementById('org-select-visible').addEventListener('click', () => {
        for (const a of getFilteredAudios().slice(0, 250)) selected.add(a.id);
        updateSelUI();
        renderItems();
    });
    $clearSel.addEventListener('click', () => {
        selected.clear();
        updateSelUI();
        renderItems();
    });

    renderAll();
})();
</script>
@endsection
