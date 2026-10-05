@extends('layouts.admin')

@section('title', 'Pengaturan')

@section('content')
    <x-admin.page-header title="Pengaturan toko" />

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" novalidate class="max-w-xl">
        @csrf
        @method('PUT')

        <fieldset class="mb-8">
            <legend class="mb-3 text-lg font-semibold">Toko</legend>
            <x-form.input name="shop_name" label="Nama toko" :value="$values['shop_name']" required maxlength="100" />
            <x-form.input name="shop_tagline" label="Deskripsi singkat" :value="$values['shop_tagline']" maxlength="150" />
            <x-form.textarea name="address" label="Alamat" :value="$values['address']" maxlength="500" />
            <x-form.textarea name="opening_hours" label="Jam operasional" :value="$values['opening_hours']" maxlength="500"
                             hint="Tulis bebas: jam buka dan tutup untuk setiap hari, atau hari libur." />
        </fieldset>

        <fieldset class="mb-8">
            <legend class="mb-3 text-lg font-semibold">Kontak</legend>
            <x-form.input name="whatsapp_number" label="Nomor WhatsApp" type="tel" inputmode="tel" autocomplete="off"
                          :value="$whatsappDisplay" maxlength="30"
                          hint="Boleh ditulis 0878-7462-7555 atau +62 878 7462 7555. Disimpan dalam format 62…" />
            @if ($whatsappTestUrl)
                <p class="-mt-2 mb-4 text-sm">
                    <a href="{{ $whatsappTestUrl }}" target="_blank" rel="noopener noreferrer" class="underline">Uji tautan WhatsApp</a>
                    <span class="text-muted">(membuka WhatsApp dengan nomor yang tersimpan)</span>
                </p>
            @endif
            <x-form.input name="instagram" label="Instagram" :value="$values['instagram']" maxlength="60"
                          hint="Username saja, tanpa @. Tautan instagram.com/… juga diterima." />
        </fieldset>

        <fieldset class="mb-8">
            <legend class="mb-3 text-lg font-semibold">Pembayaran</legend>
            <x-form.input name="bank_name" label="Nama bank" :value="$values['bank_name']" maxlength="50" />
            <x-form.input name="bank_account_number" label="Nomor rekening" :value="$values['bank_account_number']" inputmode="numeric"
                          maxlength="40" hint="Angka saja; spasi dan strip akan dihapus." />
            <x-form.input name="bank_account_name" label="Nama pemilik rekening" :value="$values['bank_account_name']" maxlength="100" />

            <div class="mb-4">
                <label for="qris" class="field-label">Gambar QRIS</label>
                <div class="flex items-start gap-3">
                    <img id="qris-preview" src="{{ $qrisUrl ?? asset('images/placeholder-produk.svg') }}" alt="Pratinjau QRIS"
                         width="96" height="96" class="size-24 shrink-0 rounded-control border border-line object-contain bg-surface">
                    <div class="min-w-0">
                        <input id="qris" name="qris" type="file" accept="image/jpeg,image/png,image/webp" data-image-input="qris-preview"
                               @error('qris') aria-invalid="true" @enderror aria-describedby="qris-hint qris-error"
                               class="field-input py-2 file:mr-3 file:border-0 file:bg-transparent file:font-medium">
                        <p id="qris-hint" class="mt-1 text-sm text-muted">JPG, PNG, atau WebP, maksimal 2 MB.</p>
                        @error('qris')
                            <p id="qris-error" class="field-error">{{ $message }}</p>
                        @enderror
                        @if ($values['qris_image'])
                            <input type="hidden" name="remove_qris" value="0">
                            <label class="mt-2 inline-flex min-h-11 cursor-pointer items-center gap-3">
                                <input type="checkbox" name="remove_qris" value="1" @checked(old('remove_qris')) class="size-5 accent-ink">
                                <span>Hapus QRIS saat ini</span>
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <x-form.textarea name="payment_instructions" label="Instruksi pembayaran" :value="$values['payment_instructions']" maxlength="500"
                             hint="Contoh: tulis kode pesanan pada berita transfer. Boleh dikosongkan." />
        </fieldset>

        <button type="submit" class="btn btn-primary">Simpan pengaturan</button>
    </form>
@endsection
