@props(['item', 'resource', 'topics' => [], 'seriesList' => null])
@php
    $isNew = ! $item->exists;
    $publishedAt = old('published_at', $item->published_at?->format('Y-m-d\TH:i'));
@endphp
<div class="card">
    <div class="card-head"><h2>النشر</h2>@unless ($isNew)<x-status :item="$item" />@endunless</div>
    <div class="card-pad fields">
        <div class="field">
            <span class="label">الحالة</span>
            <div class="seg" role="radiogroup" aria-label="الحالة">
                <label><input type="radio" name="status" value="draft" @checked(old('status', $item->status ?? 'draft') === 'draft')> مسودة</label>
                <label><input type="radio" name="status" value="published" @checked(old('status', $item->status) === 'published')> منشور</label>
            </div>
        </div>

        <x-field label="تاريخ النشر" name="published_at" hint="اتركه فاضي للنشر الآن، أو اختر تاريخ لاحق للجدولة.">
            <input type="datetime-local" id="published_at" name="published_at" value="{{ $publishedAt }}">
        </x-field>

        <x-field label="الموضوع" name="topic_id">
            <select id="topic_id" name="topic_id">
                <option value="">— بدون —</option>
                @foreach ($topics as $t)
                    <option value="{{ $t->id }}" @selected(old('topic_id', $item->topic_id) == $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </x-field>

        @if ($seriesList)
            <div class="row-2">
                <x-field label="السلسلة" name="series_id">
                    <select id="series_id" name="series_id">
                        <option value="">— بدون —</option>
                        @foreach ($seriesList as $s)
                            <option value="{{ $s->id }}" @selected(old('series_id', $item->series_id) == $s->id)>{{ $s->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="الترتيب" name="series_order" hint="رقمه في السلسلة">
                    <input type="number" id="series_order" name="series_order" min="0" value="{{ old('series_order', $item->series_order ?? 0) }}">
                </x-field>
            </div>
        @endif

        {{ $slot }}
    </div>
    <div class="form-actions">
        <button class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> {{ $isNew ? 'إضافة' : 'حفظ التعديلات' }}</button>
        <button class="btn btn-secondary" name="after" value="new">حفظ وإضافة جديد</button>
    </div>
    @unless ($isNew)
        <div class="side-meta">
            <span>أُنشئ {{ $item->created_at->diffForHumans() }}</span>
            <span>آخر تعديل {{ $item->updated_at->diffForHumans() }}</span>
        </div>
    @endunless
</div>
