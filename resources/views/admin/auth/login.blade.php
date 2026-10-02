@extends('layouts.base')

@section('title', 'Login Admin')

@section('body')
    <main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center px-4 py-10">
        <div class="mb-6 flex flex-col items-center text-center">
            <img src="{{ asset('images/logo-kedai-berkah.webp') }}" alt="Logo Kedai Berkah" width="112" height="103" class="h-auto w-28">
            <h1 class="mt-4 text-2xl font-bold">Login Admin</h1>
        </div>

        <form method="POST" action="{{ route('admin.login.store') }}" novalidate
              class="rounded-control border border-line bg-surface p-5">
            @csrf

            @error('login')
                <div role="alert" class="mb-4 rounded-control bg-danger-soft px-3 py-2 text-sm text-danger">
                    {{ $message }}
                </div>
            @enderror

            <div class="mb-4">
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" inputmode="email" class="field-input"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <p id="email-error" class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-5">
                <label for="password" class="field-label">Password</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password" class="field-input"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full">Masuk</button>
        </form>

        <p class="mt-5 text-center text-sm">
            <a href="{{ url('/') }}" class="text-muted underline hover:text-ink">Kembali ke beranda</a>
        </p>
    </main>
@endsection
