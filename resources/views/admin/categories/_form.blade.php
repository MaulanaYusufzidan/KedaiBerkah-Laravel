<form method="POST" action="{{ $action }}" novalidate class="max-w-md">
    @csrf
    @if ($category)
        @method('PUT')
    @endif

    <x-form.input name="name" label="Nama kategori" :value="$category?->name" required maxlength="100" />
    <x-form.input name="sort_order" label="Urutan tampil" type="number" :value="$category?->sort_order ?? $nextSortOrder"
                  hint="Angka kecil tampil lebih dulu." min="0" max="9999" step="1" required inputmode="numeric" />
    <x-form.checkbox name="is_active" label="Kategori aktif" :checked="$category?->is_active ?? true" />

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-quiet">Batal</a>
    </div>
</form>
