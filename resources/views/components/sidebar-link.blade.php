@props(['active', 'icon'])

@php
$classes = ($active ?? false)
            ? 'px-4 py-2 flex items-center gap-3 bg-[#001e40]/10 text-[#001e40] border-l-4 border-[#001e40] font-bold transition-all duration-200'
            : 'px-4 py-2 flex items-center gap-3 text-[#43474f] hover:bg-[#eeedf2] border-l-4 border-transparent transition-all duration-200';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} 
   :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3'"
   wire:navigate>
    @if($icon)
        <span class="material-symbols-outlined {{ ($active ?? false) ? 'fill-1' : '' }}" 
              :class="sidebarCollapsed ? 'mx-auto' : ''"
              style="{{ ($active ?? false) ? 'font-variation-settings: \'FILL\' 1;' : '' }}">
            {{ $icon }}
        </span>
    @endif
    <span class="font-body-md transition-opacity duration-200 flex-1" x-show="!sidebarCollapsed" x-transition>{{ $slot }}</span>
</a>
