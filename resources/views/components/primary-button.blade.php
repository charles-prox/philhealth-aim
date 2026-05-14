@props(['type' => 'button', 'variant' => 'primary', 'icon' => null])

@php
    $baseClasses = 'flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-semibold text-sm transition-all active:scale-[0.98] shadow-sm disabled:opacity-50 disabled:pointer-events-none group';
    
    $variants = [
        'primary' => 'bg-[#001e40] text-white hover:bg-[#003366]',
        'secondary' => 'bg-[#eeedf2] text-[#43474f] border border-[#c3c6d1] hover:border-[#001e40] hover:bg-white hover:text-[#001e40]',
        'error' => 'bg-[#ba1a1a] text-white hover:bg-[#93000a]',
        'tertiary' => 'bg-transparent text-[#001e40] hover:bg-[#f4f3f8]',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)
        <span class="material-symbols-outlined text-[20px] {{ $variant === 'primary' ? 'group-hover:translate-x-0.5 transition-transform' : '' }}">
            {{ $icon }}
        </span>
    @endif
    
    <span>{{ $slot }}</span>
</button>
