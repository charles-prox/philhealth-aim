<div x-data="{ 
    toasts: [],
    add(data) {
        // Livewire 3 might wrap the detail in an array or provide it directly
        let toast = Array.isArray(data) ? data[0] : data;
        
        toast.id = Date.now();
        this.toasts.push(toast);
        setTimeout(() => {
            this.remove(toast.id);
        }, 8000);
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    }
}" 
@toast.window="add($event.detail)"
class="fixed top-20 right-6 z-[200] flex flex-col gap-3 pointer-events-none w-full max-w-sm">
    
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="true"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-x-full opacity-0"
             x-transition:enter-end="translate-x-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="pointer-events-auto w-full bg-white border border-[#c3c6d1] rounded-2xl shadow-2xl overflow-hidden flex items-stretch ring-1 ring-[#001e40]/5">
            
            <!-- Icon/Type Area -->
            <div class="flex items-center justify-center px-4" :class="{
                'bg-emerald-50': toast.type === 'success',
                'bg-red-50': toast.type === 'error',
                'bg-[#eeedf2]': toast.type === 'info',
                'bg-amber-50': toast.type === 'warning'
            }">
                <div :class="{
                    'text-emerald-600': toast.type === 'success',
                    'text-[#ba1a1a]': toast.type === 'error',
                    'text-[#001e40]': toast.type === 'info',
                    'text-amber-600': toast.type === 'warning'
                }">
                    <span class="material-symbols-outlined text-[28px] fill-1" x-text="toast.type === 'success' ? 'check_circle' : (toast.type === 'error' ? 'error' : (toast.type === 'warning' ? 'warning' : 'info'))"></span>
                </div>
            </div>

            <div class="p-4 pr-10 flex-1 relative">
                <h4 class="text-[13px] font-bold text-[#001e40] uppercase tracking-wider mb-0.5" x-text="toast.title || (toast.type === 'success' ? 'Success' : 'Notification')"></h4>
                <p class="text-[13px] text-[#43474f] font-medium leading-tight" x-text="toast.message"></p>

                <button @click="remove(toast.id)" class="absolute top-3 right-3 text-[#c3c6d1] hover:text-[#001e40] transition-colors p-1 rounded-lg hover:bg-[#f4f3f8]">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
        </div>
    </template>
</div>
