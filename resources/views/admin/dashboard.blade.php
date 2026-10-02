@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-bold">Dashboard</h1>
    <p class="mt-1 text-muted">Halo, {{ auth()->user()->name }}.</p>

    <section class="mt-8" aria-labelledby="info-kedai">
        <h2 id="info-kedai" class="text-lg font-semibold">Informasi kedai</h2>

        <dl class="mt-3 divide-y divide-line rounded-control border border-line bg-surface">
            @foreach ($shop as $label => $value)
                <div class="grid gap-1 px-4 py-3 sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm text-muted">{{ $label }}</dt>
                    <dd class="sm:col-span-2">
                        @if ($value)
                            {{ $value }}
                        @else
                            <span class="text-muted">Belum diisi</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>
@endsection
