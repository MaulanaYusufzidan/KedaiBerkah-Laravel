<form method="POST" action="{{ $action }}" novalidate class="max-w-md">
    @csrf
    @if ($menu)
        @method('PUT')
    @endif

    @if ($menu)
        <div class="mb-4">
            <p class="field-label">Produk</p>
            <p>{{ $menu->product->name }} <span class="text-muted">· {{ $menu->product->category->name }}</span></p>
        </div>
    @else
        @if ($products->isEmpty())
            <p class="mb-4 rounded-control border border-line bg-surface px-4 py-3 text-muted">
                Belum ada produk aktif. <a href="{{ route('admin.products.create') }}" class="underline">Tambah produk</a> dulu.
            </p>
        @endif

        <x-form.select name="product_id" label="Produk" required>
            <option value="">Pilih produk</option>
            @foreach ($products as $categoryName => $items)
                <optgroup label="{{ $categoryName }}">
                    @foreach ($items as $product)
                        <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                            {{ $product->name }} ({{ \App\Support\Rupiah::format($product->base_price) }})
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </x-form.select>
    @endif

    <x-form.input name="menu_date" label="Tanggal" type="date"
                  :value="$menu ? $menu->menu_date->toDateString() : $date->toDateString()" required />
    <x-form.input name="price" label="Harga hari ini (Rp)" type="number" :value="$menu?->price"
                  :hint="$menu ? 'Angka rupiah tanpa titik.' : 'Kosongkan untuk memakai harga dasar produk.'"
                  min="0" max="10000000" step="1" inputmode="numeric" :required="(bool) $menu" />
    <x-form.input name="stock" label="Stok" type="number" :value="$menu?->stock"
                  min="0" max="100000" step="1" required inputmode="numeric" />

    <div class="grid grid-cols-2 gap-3">
        <x-form.input name="available_from" label="Tersedia mulai" type="time"
                      :value="$menu?->available_from ? substr($menu->available_from, 0, 5) : null" />
        <x-form.input name="available_until" label="Tersedia sampai" type="time"
                      :value="$menu?->available_until ? substr($menu->available_until, 0, 5) : null" />
    </div>

    <x-form.select name="status" label="Status" required>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}"
                @selected(old('status', $menu?->status?->value ?? 'available') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </x-form.select>

    <x-form.input name="notes" label="Catatan" :value="$menu?->notes" maxlength="255" hint="Boleh dikosongkan." />

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.daily-menus.index', ['date' => $menu ? $menu->menu_date->toDateString() : $date->toDateString()]) }}" class="btn btn-quiet">Batal</a>
    </div>
</form>
