@props(['filters' => [], 'topics' => [], 'seriesList' => null, 'placeholder' => 'ابحث بالعنوان أو المحتوى…'])
<form method="GET" class="toolbar" role="search">
    <div class="search">
        <i class="ti ti-search" aria-hidden="true"></i>
        <label class="sr-only" for="q">بحث</label>
        <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $placeholder }}">
    </div>
    <select name="topic" aria-label="الموضوع">
        <option value="">كل المواضيع</option>
        @foreach ($topics as $t)
            <option value="{{ $t->id }}" @selected(($filters['topic'] ?? '') == $t->id)>{{ $t->name }}</option>
        @endforeach
    </select>
    @if ($seriesList)
        <select name="series" aria-label="السلسلة">
            <option value="">كل السلاسل</option>
            @foreach ($seriesList as $s)
                <option value="{{ $s->id }}" @selected(($filters['series'] ?? '') == $s->id)>{{ $s->title }}</option>
            @endforeach
        </select>
    @endif
    <select name="status" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="published" @selected(($filters['status'] ?? '') === 'published')>منشور</option>
        <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>مسودة</option>
    </select>
    <button class="btn btn-secondary btn-sm">بحث</button>
    @if (array_filter($filters))
        <a href="{{ url()->current() }}" class="btn btn-ghost btn-sm">مسح الفلاتر</a>
    @endif
</form>
