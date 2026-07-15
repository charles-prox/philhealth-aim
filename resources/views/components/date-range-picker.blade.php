@props([
    'placeholder' => 'Select date range...'
])

<div x-data="{
    value: @entangle($attributes->wire('model')),
    instance: null,
    init() {
        if (typeof flatpickr === 'undefined') {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css';
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/flatpickr';
            script.onload = () => this.setup();
            document.head.appendChild(script);
        } else {
            this.setup();
        }
    },
    setup() {
        // 🛡️ UI Guard: Calculate tomorrow's date to disable today and all past dates
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);

        this.instance = flatpickr(this.$refs.input, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            minDate: tomorrow, // Restricts calendar selection to tomorrow onwards
            defaultDate: this.value ? this.value.split(' to ') : null,
            onClose: (selectedDates, dateStr) => {
                this.value = dateStr;
            }
        });

        this.$watch('value', val => {
            if (!val) {
                this.instance.clear();
            } else if (this.instance) {
                this.instance.setDate(val.split(' to '), false);
            }
        });
    }
}" class="relative">
    
    <div class="relative">
        <input 
            x-ref="input"
            type="text" 
            readonly
            placeholder="{{ $placeholder }}"
            class="w-full text-xs p-2.5 pl-10 bg-surface border border-outline-variant rounded-lg text-on-surface font-semibold focus:outline-primary cursor-pointer shadow-sm"
        />
        
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-outline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
    </div>
</div>
