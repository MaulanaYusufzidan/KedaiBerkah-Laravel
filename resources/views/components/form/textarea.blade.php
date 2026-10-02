@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 3])

<div class="mb-4">
    <label for="{{ $name }}" class="field-label">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              @error($name) aria-invalid="true" @enderror
              aria-describedby="{{ $name }}-hint {{ $name }}-error"
              {{ $attributes->merge(['class' => 'field-input py-2']) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p id="{{ $name }}-hint" class="mt-1 text-sm text-muted">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="field-error">{{ $message }}</p>
    @enderror
</div>
