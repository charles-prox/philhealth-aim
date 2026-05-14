@props(['variant' => 'secondary', 'icon' => null])

@php
    $baseClasses = 'flex items-center justify-center w-[42px] h-[42px] rounded-lg transition-all active:scale-[0.95] shadow-sm disabled:opacity-50 disabled:pointer-events-none group';
    
    $variants = [
        'primary' => 'bg-[#001e40] text-white hover:bg-[#003366]',
        'secondary' => 'bg-white text-[#43474f] border border-[#c3c6d1] hover:border-[#001e40] hover:text-[#001e40] shadow-sm',
        'error' => 'bg-[#ba1a1a] text-white hover:bg-[#93000a]',
        'tertiary' => 'bg-transparent text-[#43474f] hover:bg-[#f4f3f8]',
        'success' => 'bg-green-700 text-white hover:bg-green-800',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['secondary']);
@endphp

@if($attributes->has('href'))
    <a {{ $attributes->merge(['class' => $classes]) }}>
        <span class="material-symbols-outlined text-[20px] leading-none">{{ $icon ?? $slot }}</span>
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>
        <span class="material-symbols-outlined text-[20px] leading-none">{{ $icon ?? $slot }}</span>
    </button>
@endif
