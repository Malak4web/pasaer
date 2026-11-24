@props(['paginator'])
@if ($paginator->total() > 0)
<div class="pager">
    <span>عرض {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} من {{ $paginator->total() }}</span>
    @if ($paginator->hasPages())
        <div class="pages">
            @if ($paginator->onFirstPage())
                <span class="off" aria-hidden="true"><i class="ti ti-chevron-right"></i></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" aria-label="السابق"><i class="ti ti-chevron-right"></i></a>
            @endif
            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="cur" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" aria-label="التالي"><i class="ti ti-chevron-left"></i></a>
            @else
                <span class="off" aria-hidden="true"><i class="ti ti-chevron-left"></i></span>
            @endif
        </div>
    @endif
</div>
@endif
