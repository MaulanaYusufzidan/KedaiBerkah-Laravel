@props(['series', 'label'])

@php
    $max = max(array_column($series, 'revenue')) ?: 1;
    $count = count($series);
    $mid = intdiv($count - 1, 2);
@endphp

<div>
    <p class="mb-1 text-sm text-muted">{{ \App\Support\Rupiah::short($max) }}</p>

    <div role="img" aria-label="{{ $label }}"
         class="flex h-44 items-end gap-px border-b border-line">
        @foreach ($series as $point)
            <div class="flex h-full min-w-0 flex-1 items-end"
                 title="{{ $point['title'] }}: {{ \App\Support\Rupiah::format($point['revenue']) }}, {{ $point['orders'] }} pesanan">
                <div class="w-full bg-chart" style="height: {{ $point['revenue'] > 0 ? max(round($point['revenue'] / $max * 100, 1), 1.5) : 0 }}%"></div>
            </div>
        @endforeach
    </div>

    <div class="mt-1 flex justify-between text-sm text-muted" aria-hidden="true">
        <span>{{ $series[0]['label'] }}</span>
        @if ($count > 2)
            <span>{{ $series[$mid]['label'] }}</span>
        @endif
        @if ($count > 1)
            <span>{{ $series[$count - 1]['label'] }}</span>
        @endif
    </div>

    <details class="mt-3">
        <summary class="cursor-pointer text-sm underline">Lihat data grafik</summary>
        <div class="mt-2 max-h-72 overflow-auto rounded-control border border-line">
            <table class="w-full text-left text-sm">
                <thead class="bg-canvas">
                    <tr>
                        <th scope="col" class="px-3 py-2 font-medium">Periode</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Pesanan</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Omzet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($series as $point)
                        <tr>
                            <td class="px-3 py-2">{{ $point['title'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $point['orders'] }}</td>
                            <td class="px-3 py-2 text-right">{{ \App\Support\Rupiah::format($point['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</div>
