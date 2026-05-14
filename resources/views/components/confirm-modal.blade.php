<div x-data="{ 
    show: false,
    title: '',
    message: '',
    type: 'info',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    onConfirm: null,
    
    handleConfirm(data) {
        this.title = data.title || 'Are you sure?';
        this.message = data.message || '';
        this.type = data.type || 'info';
        this.confirmLabel = data.confirmLabel || 'Confirm';
        this.cancelLabel = data.cancelLabel || 'Cancel';
        this.onConfirm = data.onConfirm;
        this.show = true;
    },
    
    confirm() {
        if (this.onConfirm && typeof this.onConfirm === 'function') {
            this.onConfirm();
        }
        this.show = false;
    }
}" 
@confirm.window="handleConfirm($event.detail)"
class="relative z-[300]"
x-cloak>
    
    <div x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-[#001e40]/60 backdrop-blur-sm"></div>

    <div x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="fixed inset-0 z-10 flex items-center justify-center p-4">
        
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-[#c3c6d1]">
            <div class="p-8 text-center">
                <!-- Icon based on type -->
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6"
                     :class="{
                        'bg-red-50': type === 'danger',
                        'bg-amber-50': type === 'warning',
                        'bg-emerald-50': type === 'success',
                        'bg-[#f4f3f8]': type === 'info'
                     }">
                    <span class="material-symbols-outlined text-[40px] fill-1"
                          :class="{
                            'text-[#ba1a1a]': type === 'danger',
                            'text-amber-600': type === 'warning',
                            'text-emerald-600': type === 'success',
                            'text-[#001e40]': type === 'info'
                          }"
                          x-text="type === 'danger' ? 'delete' : (type === 'warning' ? 'warning' : (type === 'success' ? 'check_circle' : 'help'))">
                    </span>
                </div>

                <h3 class="text-xl font-bold text-[#001e40]" x-text="title"></h3>
                <p class="text-[14px] text-[#43474f] mt-3 leading-relaxed" x-text="message"></p>
            </div>

            <div class="p-6 bg-[#f9f9fe] border-t border-[#eeedf2] flex gap-3">
                <button @click="show = false" 
                        class="flex-1 px-4 py-3 border border-[#c3c6d1] text-[#43474f] font-bold rounded-xl hover:bg-white hover:border-[#001e40] hover:text-[#001e40] transition-all active:scale-95">
                    <span x-text="cancelLabel"></span>
                </button>
                <button @click="confirm()" 
                        class="flex-1 px-4 py-3 font-bold rounded-xl transition-all shadow-lg active:scale-95 text-white"
                        :class="{
                            'bg-[#ba1a1a] hover:bg-[#93000a]': type === 'danger',
                            'bg-amber-600 hover:bg-amber-700': type === 'warning',
                            'bg-emerald-600 hover:bg-emerald-700': type === 'success',
                            'bg-[#001e40] hover:bg-[#003366]': type === 'info'
                        }">
                    <span x-text="confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>
</div>
