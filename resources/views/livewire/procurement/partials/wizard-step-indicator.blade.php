<!-- Wizard Step Indicator -->
<div class="bg-white border border-[#eeedf2] rounded-2xl p-5 shadow-2xs">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
            {{ $currentStep === 1 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 1 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                {{ $currentStep === 1 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 1 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                @if($currentStep > 1) <span class="material-symbols-outlined text-[16px] font-bold">check</span> @else 1 @endif
            </div>
            <div class="space-y-0.5">
                <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 1 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Browse APP Catalog</p>
                <p class="text-[9px] text-[#43474f]/60 leading-tight">Select official line items</p>
            </div>
        </div>

        <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
            {{ $currentStep === 2 ? 'bg-[#001e40]/5 border-[#001e40]/20' : ($currentStep > 2 ? 'bg-green-50/40 border-green-100/60 opacity-80' : 'bg-transparent border-transparent opacity-50') }}">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                {{ $currentStep === 2 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : ($currentStep > 2 ? 'bg-green-600 text-white' : 'bg-[#eeedf2] text-[#43474f]') }}">
                @if($currentStep > 2) <span class="material-symbols-outlined text-[16px] font-bold">check</span> @else 2 @endif
            </div>
            <div class="space-y-0.5">
                <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep >= 2 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Configure PR & Items</p>
                <p class="text-[9px] text-[#43474f]/60 leading-tight">Set details, qty & purpose</p>
            </div>
        </div>

        <div class="flex items-center gap-3 p-3 rounded-xl border transition-all duration-300
            {{ $currentStep === 3 ? 'bg-[#001e40]/5 border-[#001e40]/20' : 'bg-transparent border-transparent opacity-50' }}">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs transition-all duration-300
                {{ $currentStep === 3 ? 'bg-[#001e40] text-white ring-4 ring-[#001e40]/10' : 'bg-[#eeedf2] text-[#43474f]' }}">
                3
            </div>
            <div class="space-y-0.5">
                <p class="text-[11px] font-bold uppercase tracking-wider {{ $currentStep === 3 ? 'text-[#001e40]' : 'text-[#43474f]/70' }}">Review & Submit</p>
                <p class="text-[9px] text-[#43474f]/60 leading-tight">Verify PR bundle</p>
            </div>
        </div>
    </div>
</div>
