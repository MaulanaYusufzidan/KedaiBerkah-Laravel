@extends('layouts.base')

@section('body')
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:bg-surface focus:px-3 focus:py-2">Lewati ke konten</a>

    <header class="border-b border-line bg-surface">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-kedai-berkah.webp') }}" alt="" width="40" height="37" class="h-10 w-auto">
                <div class="leading-tight">
                    <p class="font-semibold">Kedai Berkah</p>
                    <p class="text-sm text-muted">Admin</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-quiet">Keluar</button>
            </form>

            <nav aria-label="Menu admin" class="flex w-full flex-wrap gap-x-6">
                @foreach ([
                    ['Dashboard', 'admin.dashboard', 'admin.dashboard'],
                    ['Pesanan', 'admin.orders.index', 'admin.orders.*'],
                    ['Kategori', 'admin.categories.index', 'admin.categories.*'],
                    ['Produk', 'admin.products.index', 'admin.products.*'],
                    ['Menu Hari Ini', 'admin.daily-menus.index', 'admin.daily-menus.*'],
                    ['Area Pengiriman', 'admin.delivery-areas.index', 'admin.delivery-areas.*'],
                    ['Pengaturan', 'admin.settings.edit', 'admin.settings.*'],
                ] as [$label, $route, $pattern])
                    <a href="{{ route($route) }}"
                       @class([
                           'inline-block border-b-2 py-2 text-base font-medium',
                           'border-brand text-ink' => request()->routeIs($pattern),
                           'border-transparent text-muted hover:text-ink' => ! request()->routeIs($pattern),
                       ])
                       @if (request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="konten" class="mx-auto max-w-4xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-5 rounded-control border border-success/30 bg-success-soft px-4 py-3 text-success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="mb-5 rounded-control border border-danger/30 bg-danger-soft px-4 py-3 text-danger">{{ session('error') }}</div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto max-w-4xl px-4 pb-8 text-sm text-muted">
        Kedai Berkah · Area admin
    </footer>
@endsection
