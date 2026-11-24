@props(['item'])
@php
    $state = $item->status !== 'published' ? 'draft' : ($item->published_at && $item->published_at->isFuture() ? 'scheduled' : 'published');
    $labels = ['published' => 'منشور', 'draft' => 'مسودة', 'scheduled' => 'مجدول'];
    $icons = ['published' => 'ti-circle-check', 'draft' => 'ti-pencil', 'scheduled' => 'ti-clock'];
@endphp
<span class="badge badge-{{ $state }}" @if ($state === 'scheduled') title="{{ $item->published_at->translatedFormat('j F Y، H:i') }}" @endif>
    <i class="ti {{ $icons[$state] }}" aria-hidden="true"></i> {{ $labels[$state] }}
</span>
