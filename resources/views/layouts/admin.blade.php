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

            <form method="POST" action="{{ route('admin.logout') }}" class="sm:order-3">
                @csrf
                <button type="submit" class="btn btn-quiet">Keluar</button>
            </form>

            <nav aria-label="Menu admin" class="w-full sm:order-2 sm:w-auto sm:flex-1">
                <a href="{{ route('admin.dashboard') }}"
                   @class([
                       'inline-block border-b-2 py-2 text-base font-medium',
                       'border-brand text-ink' => request()->routeIs('admin.dashboard'),
                       'border-transparent text-muted hover:text-ink' => ! request()->routeIs('admin.dashboard'),
                   ])
                   @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>Dashboard</a>
            </nav>
        </div>
    </header>

    <main id="konten" class="mx-auto max-w-4xl px-4 py-8">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-4xl px-4 pb-8 text-sm text-muted">
        Kedai Berkah · Area admin
    </footer>
@endsection
