@props(['label', 'icon' => null, 'error' => null])

<div class="flex flex-col gap-2">
    @if($label)
        <div class="flex justify-between items-center">
            <label {{ $attributes->only('for') }} class="text-[12px] text-[#43474f] font-bold uppercase tracking-wide">
                {{ $label }}
                @if($attributes->has('required'))
                    <span class="text-red-500 ml-0.5">*</span>
                @endif
            </label>
            @if(isset($label_right))
                {{ $label_right }}
            @endif
        </div>
    @endif
    <div class="relative" x-data="{ show: false }">
        @if($icon)
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[20px]">
                {{ $icon }}
            </span>
        @endif
        
        <input {{ $attributes->except(['label', 'icon', 'error'])->class([
            'w-full py-2.5 bg-white border border-[#c3c6d1] rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm',
            'pl-10' => $icon,
            'pr-10' => $attributes->get('type') === 'password',
            'px-4' => !$icon && $attributes->get('type') !== 'password',
            'border-red-500' => $error,
        ]) }} 
        :type="show ? 'text' : '{{ $attributes->get('type', 'text') }}'" />

        @if($attributes->get('type') === 'password')
            <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#43474f] hover:text-[#001e40] transition-colors focus:outline-none">
                <span class="material-symbols-outlined text-[20px]" x-text="show ? 'visibility_off' : 'visibility'"></span>
            </button>
        @endif
    </div>
    @if($error)
        <p class="text-xs text-red-600 mt-1">{{ $error }}</p>
    @endif
</div>
