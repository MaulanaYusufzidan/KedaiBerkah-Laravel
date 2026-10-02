@props(['edit', 'destroy', 'confirm', 'toggle' => null, 'active' => true])

<div class="flex flex-wrap gap-2">
    <a href="{{ $edit }}" class="btn btn-quiet">Edit</a>

    @if ($toggle)
        <form method="POST" action="{{ $toggle }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-quiet">{{ $active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
        </form>
    @endif

    <form method="POST" action="{{ $destroy }}" data-confirm="{{ $confirm }}">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Hapus</button>
    </form>
</div>
