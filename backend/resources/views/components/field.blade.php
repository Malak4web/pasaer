@props(['label' => null, 'name', 'hint' => null, 'required' => false, 'for' => null])
<div {{ $attributes->class(['field', 'has-error' => $errors->has($name)]) }}>
    @if ($label)
        <label for="{{ $for ?? $name }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    {{ $slot }}
    @error($name)<span class="error">{{ $message }}</span>@enderror
    @if ($hint && ! $errors->has($name))<span class="hint">{{ $hint }}</span>@endif
</div>
