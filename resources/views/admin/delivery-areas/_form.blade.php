<form method="POST" action="{{ $action }}" novalidate class="max-w-md">
    @csrf
    @if ($area)
        @method('PUT')
    @endif

    @if ($area && $area->orders_count > 0)
        <p class="mb-4 rounded-control border border-line bg-surface px-4 py-3 text-sm">
            Area ini sudah dipakai {{ $area->orders_count }} pesanan. Mengubah nama atau biaya kirim hanya berlaku untuk pesanan baru;
            pesanan lama tetap memakai data saat pesanan dibuat.
        </p>
    @endif

    <x-form.input name="district" label="Nama area" :value="$area?->district" required maxlength="100"
                  hint="Kecamatan atau wilayah pengantaran." />
    <x-form.input name="delivery_fee" label="Biaya kirim (Rp)" type="number" :value="$area?->delivery_fee"
                  hint="Angka rupiah tanpa titik, contoh: 5000. Isi 0 jika gratis."
                  min="0" max="1000000" step="1" required inputmode="numeric" />
    <x-form.checkbox name="is_active" label="Area aktif (bisa dipilih untuk pesanan baru)" :checked="$area?->is_active ?? true" />

    <div class="flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('admin.delivery-areas.index') }}" class="btn btn-quiet">Batal</a>
    </div>
</form>
