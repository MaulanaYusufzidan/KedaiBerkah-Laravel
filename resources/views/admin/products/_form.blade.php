<form method="POST" action="{{ $action }}" novalidate class="max-w-md">
    @csrf
    @if ($product)
        @method('PUT')
    @endif

    @if ($categories->isEmpty())
        <p class="mb-4 rounded-control border border-line bg-surface px-4 py-3 text-muted">
            Belum ada kategori. <a href="{{ route('admin.categories.create') }}" class="underline">Tambah kategori</a> dulu.
        </p>
    @endif

    <x-form.select name="category_id" label="Kategori" required>
        <option value="">Pilih kategori</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected((string) old('category_id', $product?->category_id) === (string) $category->id)>
                {{ $category->name }}@unless ($category->is_active) (nonaktif)@endunless
            </option>
        @endforeach
    </x-form.select>

    <x-form.input name="name" label="Nama produk" :value="$product?->name" required maxlength="150" />
    <x-form.textarea name="description" label="Deskripsi" :value="$product?->description" maxlength="1000"
                     hint="Boleh dikosongkan." />
    <x-form.input name="base_price" label="Harga dasar (Rp)" type="number" :value="$product?->base_price"
                  hint="Angka rupiah tanpa titik, contoh: 15000. Harga per hari bisa diatur di Menu Hari Ini."
                  min="0" max="10000000" step="1" required inputmode="numeric" />
    <x-form.checkbox name="is_active" label="Produk aktif" :checked="$product?->is_active ?? true" />

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-quiet">Batal</a>
    </div>
</form>
