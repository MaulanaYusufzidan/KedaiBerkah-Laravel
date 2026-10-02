@props(['name', 'label', 'required' => false, 'hint' => null])

<div class="mb-4">
    <label for="{{ $name }}" class="field-label">{{ $label }}@if ($required) <span class="text-muted font-normal">(wajib)</span>@endif</label>
    <select id="{{ $name }}" name="{{ $name }}" @if ($required) required @endif
            @error($name) aria-invalid="true" @enderror
            aria-describedby="{{ $name }}-hint {{ $name }}-error"
            {{ $attributes->merge(['class' => 'field-input']) }}>
        {{ $slot }}
    </select>
    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-sm text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
