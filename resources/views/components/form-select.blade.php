@props(['label', 'icon' => null, 'placeholder' => 'Select an option', 'options' => [], 'error' => null, 'multiple' => false, 'searchable' => false, 'placement' => 'bottom'])

<div class="flex flex-col gap-2" 
     data-options="{{ json_encode($options) }}"
     x-data="{ 
        open: false, 
        multiple: {{ $multiple ? 'true' : 'false' }},
        selected: @entangle($attributes->wire('model')),
        searchTerm: '',
        options: {{ json_encode($options) }},
        
        init() {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'data-options') {
                        this.options = JSON.parse(this.$el.getAttribute('data-options') || '{}');
                    }
                });
            });
            observer.observe(this.$el, { attributes: true });
        },
        
        get filteredOptions() {
            let result = [];
            for (const [key, value] of Object.entries(this.options)) {
                if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                    // It's a group
                    let groupItems = Object.entries(value);
                    if (this.searchTerm) {
                        groupItems = groupItems.filter(([k, v]) => String(v).toLowerCase().includes(this.searchTerm.toLowerCase()));
                    }
                    if (groupItems.length > 0) {
                        result.push({ isGroup: true, label: key, items: groupItems });
                    }
                } else {
                    // It's a regular option
                    if (!this.searchTerm || String(value).toLowerCase().includes(this.searchTerm.toLowerCase())) {
                        result.push({ isGroup: false, value: key, label: value });
                    }
                }
            }
            return result;
        },

        get displayLabel() {
            let selectedArray = Array.isArray(this.selected) ? this.selected : (this.selected ? [this.selected] : []);
            
            const findLabel = (val) => {
                for (const [key, value] of Object.entries(this.options)) {
                    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                        if (value[val] !== undefined) return String(value[val]).replace(' (Division Proper)', '');
                    } else {
                        if (key == val) return String(value).replace(' (Division Proper)', '');
                    }
                }
                return val;
            };
            
            if (this.multiple) {
                if (selectedArray.length === 0) return '{{ $placeholder }}';
                if (selectedArray.length === 1) return findLabel(selectedArray[0]);
                return selectedArray.length + ' selected';
            }
            return this.selected ? findLabel(this.selected) : '{{ $placeholder }}';
        },

        select(val) {
            if (this.multiple) {
                // Ensure selected is an array
                if (!Array.isArray(this.selected)) this.selected = [];
                
                if (this.selected.includes(val)) {
                    this.selected = this.selected.filter(i => i !== val);
                } else {
                    this.selected.push(val);
                }
            } else {
                this.selected = val;
                this.open = false;
            }
        },

        isSelected(val) {
            if (this.multiple) {
                return Array.isArray(this.selected) && this.selected.includes(val);
            }
            return this.selected == val;
        }
     }">
    @if($label)
        <div class="flex justify-between items-center">
            <label class="text-[12px] text-[#43474f] font-bold uppercase tracking-wide">
                {{ $label }}
                @if($attributes->has('required'))
                    <span class="text-red-500 ml-0.5">*</span>
                @endif
            </label>
        </div>
    @endif

    <div class="relative" @click.away="open = false">
        <!-- Trigger Button -->
        <button type="button" 
                @click="open = !open" 
                {{ $attributes->except(['label', 'icon', 'error', 'options', 'placeholder'])->class([
                    'w-full py-2.5 bg-white border rounded-lg focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] transition-all text-sm flex items-center justify-between hover:border-[#001e40] group',
                    'border-red-500' => $error,
                    'border-[#c3c6d1]' => !$error,
                    'pl-10' => $icon,
                    'px-4' => !$icon,
                    'pr-4' => true,
                ]) }}>
            <div class="flex items-center min-w-0 flex-1">
                @if($icon)
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[20px] group-hover:text-[#001e40] transition-colors">
                        {{ $icon }}
                    </span>
                @endif
                <span class="text-[#1a1c1f] whitespace-nowrap truncate pr-2" x-text="displayLabel"></span>
            </div>
            <span class="material-symbols-outlined inline-block text-[#43474f] text-[20px] transition-transform duration-200 flex-shrink-0" :class="open ? 'rotate-180' : 'rotate-0'">
                expand_more
            </span>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open" 
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             class="absolute z-[60] w-full bg-white border border-[#c3c6d1] rounded-xl shadow-xl overflow-hidden flex flex-col {{ $placement === 'top' ? 'bottom-full mb-2' : 'mt-2' }}"
             style="display: none;">
            
            @if($searchable)
                <div class="p-2 border-b border-[#eeedf2] bg-[#f9f9fe]">
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                        <input type="text" x-model="searchTerm" placeholder="Search..." 
                               class="w-full pl-8 pr-3 py-1.5 bg-white border border-[#c3c6d1] rounded-md text-xs focus:ring-1 focus:ring-[#001e40] focus:border-[#001e40]">
                    </div>
                </div>
            @endif

            <div class="max-h-60 overflow-y-auto custom-scrollbar p-1">
                <template x-for="(item, index) in filteredOptions" :key="index">
                    <div>
                        <template x-if="item.isGroup">
                            <div class="mb-1">
                                <div class="px-3 py-2 mt-1 text-[11px] font-bold text-[#43474f] uppercase tracking-wider bg-[#f4f3f8] rounded-md" x-text="item.label"></div>
                                <div class="mt-1 flex flex-col gap-0.5">
                                    <template x-for="[val, lab] in item.items" :key="val">
                                        <button type="button" 
                                                @click="select(val)"
                                                class="w-full text-left px-3 pl-4 py-2 text-sm text-[#43474f] hover:bg-[#eeedf2] hover:text-[#001e40] transition-colors flex items-center justify-between rounded-lg group">
                                            <div class="flex-1 min-w-0 pr-2">
                                                <template x-if="lab.includes(' — ')">
                                                    <div class="flex flex-col py-0.5">
                                                        <span class="font-bold text-sm" :class="isSelected(val) ? 'text-[#001e40]' : 'text-[#1a1c1f]'" x-text="lab.split(' — ')[0]"></span>
                                                        <span class="text-xs text-[#43474f]/70 group-hover:text-[#001e40]/70" x-text="lab.split(' — ')[1]"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!lab.includes(' — ')">
                                                    <span class="whitespace-nowrap truncate block" :class="isSelected(val) ? 'font-bold text-[#001e40]' : ''" x-text="lab"></span>
                                                </template>
                                            </div>
                                            <span x-show="isSelected(val)" class="material-symbols-outlined text-[18px] text-[#001e40] flex-shrink-0">check</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="!item.isGroup">
                            <button type="button" 
                                    @click="select(item.value)"
                                    class="w-full text-left px-3 py-2 text-sm text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] transition-colors flex items-center justify-between rounded-lg group">
                                <div class="flex-1 min-w-0 pr-2">
                                    <template x-if="item.label.includes(' — ')">
                                        <div class="flex flex-col py-0.5">
                                            <span class="font-bold text-sm" :class="isSelected(item.value) ? 'text-[#001e40]' : 'text-[#1a1c1f]'" x-text="item.label.split(' — ')[0]"></span>
                                            <span class="text-xs text-[#43474f]/70 group-hover:text-[#001e40]/70" x-text="item.label.split(' — ')[1]"></span>
                                        </div>
                                    </template>
                                    <template x-if="!item.label.includes(' — ')">
                                        <span class="whitespace-nowrap truncate block" :class="isSelected(item.value) ? 'font-bold text-[#001e40]' : ''" x-text="item.label"></span>
                                    </template>
                                </div>
                                <span x-show="isSelected(item.value)" class="material-symbols-outlined text-[18px] text-[#001e40] flex-shrink-0">check</span>
                            </button>
                        </template>
                    </div>
                </template>
                <div x-show="filteredOptions.length === 0" class="p-4 text-center text-xs text-[#43474f] italic">
                    No results found
                </div>
            </div>
        </div>
    </div>
</div>

@if($error)
    <p class="text-xs text-red-600 mt-1">{{ $error }}</p>
@endif
