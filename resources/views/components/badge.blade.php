@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'inline-block rounded-control border px-2 py-0.5 text-sm font-medium',
    'border-line bg-canvas text-muted' => $tone === 'neutral',
    'border-success/30 bg-success-soft text-success' => $tone === 'success',
    'border-danger/30 bg-danger-soft text-danger' => $tone === 'danger',
    'border-warning/30 bg-warning-soft text-warning' => $tone === 'warning',
]) }}>{{ $slot }}</span>
