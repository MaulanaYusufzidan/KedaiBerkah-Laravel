<form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate class="max-w-md">
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
    <div class="mb-4">
        <label for="image" class="field-label">Foto produk</label>
        <div class="flex items-start gap-3">
            <img id="image-preview" src="{{ $product ? $product->imageUrl() : asset('images/placeholder-produk.svg') }}"
                 alt="Pratinjau foto produk" width="96" height="96" class="size-24 shrink-0 rounded-control border border-line object-cover">
            <div class="min-w-0">
                <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" data-image-input="image-preview"
                       @error('image') aria-invalid="true" @enderror aria-describedby="image-hint image-error"
                       class="field-input py-2 file:mr-3 file:border-0 file:bg-transparent file:font-medium">
                <p id="image-hint" class="mt-1 text-sm text-muted">JPG, PNG, atau WebP, maksimal 2 MB. Foto akan dikecilkan otomatis.</p>
                @error('image')
                    <p id="image-error" class="field-error">{{ $message }}</p>
                @enderror
                @if ($product?->image)
                    <input type="hidden" name="remove_image" value="0">
                    <label class="mt-2 inline-flex min-h-11 cursor-pointer items-center gap-3">
                        <input type="checkbox" name="remove_image" value="1" @checked(old('remove_image')) class="size-5 accent-ink">
                        <span>Hapus foto saat ini</span>
                    </label>
                @endif
            </div>
        </div>
    </div>

    <x-form.checkbox name="is_active" label="Produk aktif" :checked="$product?->is_active ?? true" />

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-quiet">Batal</a>
    </div>
</form>
