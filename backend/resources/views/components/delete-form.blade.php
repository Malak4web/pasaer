@props(['action', 'label' => 'هذا العنصر', 'button' => false])
<form method="POST" action="{{ $action }}" data-confirm="متأكد إنك عايز تحذف {{ $label }}؟ مش هينفع ترجّعه.">
    @csrf
    @method('DELETE')
    @if ($button)
        <button class="btn btn-danger" style="width:100%"><i class="ti ti-trash" aria-hidden="true"></i> حذف</button>
    @else
        <button class="icon-btn danger" aria-label="حذف {{ $label }}" title="حذف"><i class="ti ti-trash"></i></button>
    @endif
</form>
