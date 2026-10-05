@props(['product', 'size' => 'size-14'])

<img src="{{ $product->imageUrl() }}" alt="" width="56" height="56" loading="lazy" decoding="async"
     {{ $attributes->class([$size, 'shrink-0 rounded-control border border-line object-cover']) }}>
