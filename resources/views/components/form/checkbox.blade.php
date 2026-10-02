@props(['name', 'label', 'checked' => false])

<div class="mb-4">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex min-h-11 cursor-pointer items-center gap-3">
        <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked))
               class="size-5 accent-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">
        <span>{{ $label }}</span>
    </label>
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
