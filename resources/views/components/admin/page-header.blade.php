@props(['title'])

<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ $title }}</h1>
    {{ $slot }}
</div>
