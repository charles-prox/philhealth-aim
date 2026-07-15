<div class="bg-white border border-[#c3c6d1] rounded-2xl p-8 shadow-sm mt-8 mb-6 space-y-4"
     x-data="{
        basket: $wire.entangle('basket'),
        availableBudget: {{ $this->availableBudget }},
        formatPrice(val) {
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);
        },
        get totalValue() {
            return Object.values(this.basket || {}).reduce((sum, item) => {
                if (!item) return sum;
                return sum + (parseFloat(item.qty || 0) * parseFloat(item.unit_cost || 0));
            }, 0);
        }
     }">
    <div class="border-b border-[#eeedf2] pb-4">
        <h3 class="text-xl font-bold text-[#001e40]">Enter PR Metadata & Items Details</h3>
        <p class="text-xs text-[#43474f] mt-1">Configure item descriptions, quantities, and cost alongside references.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
        {{-- Form inputs --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            
            {{-- Metadata Fields --}}
            <div class="bg-[#f9f9fe] border border-[#eeedf2] p-5 rounded-2xl space-y-4">
                <h4 class="text-sm font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">info</span> PR General Info
                </h4>
                
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">System Tracking Number</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f]/60 text-[18px]">lock</span>
                        <input type="text" wire:model="form.trackingNumber" readonly disabled
                               class="w-full pl-9 pr-4 py-3 bg-[#eeedf2]/50 border border-[#c3c6d1] rounded-xl text-sm outline-none transition-all font-mono font-bold text-[#43474f]/70 cursor-not-allowed"/>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Recommended By <span class="text-[#ba1a1a]">*</span></label>
                        <x-form-select label="" placeholder="Select Recommending Officer..." icon="recommend" searchable wire:model="form.recommendedById" :options="$this->validRecommenders->toArray()" :disabled="$inputsDisabled" />
                        @if($this->validRecommenders->isEmpty())
                            <p class="text-[10px] text-amber-600 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">warning</span> No authorized recommenders configured in the Signatory Registry. Contact your system administrator.</p>
                        @endif
                        @error('form.recommendedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Approved By <span class="text-[#ba1a1a]">*</span></label>
                        <x-form-select label="" placeholder="Select Approving Officer..." icon="person_check" searchable wire:model="form.approvedById" :options="$this->validApprovers->toArray()" :disabled="$inputsDisabled" />
                        @if($this->validApprovers->isEmpty())
                            <p class="text-[10px] text-amber-600 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">warning</span> No authorized approving officers configured in the Signatory Registry. Contact your system administrator.</p>
                        @endif
                        @error('form.approvedById') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-[#43474f] mb-1.5">Purpose / Justification <span class="text-[#ba1a1a]">*</span></label>
                    <textarea wire:model="form.purpose" placeholder="Provide the operational justification..." class="w-full px-4 py-3 bg-white border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none min-h-[100px]" {{ $inputsDisabled ? 'disabled' : '' }}></textarea>
                    @error('form.purpose') <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Item Specifications --}}
            <div class="space-y-4">
                <h4 class="text-sm font-bold text-[#001e40] uppercase tracking-wider flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span> PR Line Items Configuration
                </h4>

                <div class="space-y-4">
                    @php
                        $appLineItem = \App\Models\AppLineItem::find($selectedAppLineId);
                        $available = 0;
                        if ($appLineItem) {
                            $available = $appLineItem->approved_budget - $appLineItem->utilized_budget;
                            if ($this->folderId) {
                                $existingCost = \App\Models\PrItem::where('folder_id', $this->folderId)
                                    ->where('app_line_item_id', $selectedAppLineId)
                                    ->sum('estimated_total_cost');
                                $available += $existingCost;
                            }
                        }
                    @endphp
                    @if($appLineItem)
                        <div class="p-5 border border-[#c3c6d1] rounded-2xl bg-white space-y-4 shadow-2xs relative">
                            <div class="flex justify-between items-start border-b border-[#eeedf2] pb-3">
                                <div>
                                    <h5 class="font-bold text-sm text-[#001e40]">PR Items List</h5>
                                    <p class="text-[10px] text-[#43474f]/70 mt-0.5 uppercase tracking-wider font-semibold">
                                        Funded by APP Line remaining budget: <span class="font-bold text-green-700">₱{{ number_format($available, 2) }} available</span>
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-4 divide-y divide-[#eeedf2] -mt-2">
                                @forelse($basket as $basketKey => $basketItem)
                                    <div class="pt-4 first:pt-2 space-y-3" x-init="if (!basket['{{ $basketKey }}']) basket['{{ $basketKey }}'] = { description: '', unit: 'pcs', qty: 1, unit_cost: 0.00 }">
                                        <div class="flex justify-between items-center border-b border-[#eeedf2]/60 pb-2">
                                            <span class="text-[10px] font-bold text-[#001e40] uppercase tracking-wider">Item #{{ $loop->iteration }} Details</span>
                                            @if(!$inputsDisabled)
                                                <button type="button" wire:click="removeItemRow('{{ $basketKey }}')" class="px-3 py-1 bg-[#ba1a1a]/10 hover:bg-[#ba1a1a]/20 text-[#ba1a1a] border border-[#ba1a1a]/20 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1 active:scale-95">
                                                    <span class="material-symbols-outlined text-[14px]">delete</span> Remove Row
                                                </button>
                                            @endif
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <!-- Left Column: Particulars / Description (textarea) -->
                                            <div>
                                                <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Item Particulars <span class="text-[#ba1a1a]">*</span></label>
                                                <textarea wire:model.blur="basket.{{ $basketKey }}.description" x-model="basket['{{ $basketKey }}']['description']" placeholder="Enter detailed item particulars/description..." rows="3" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40] resize-y" {{ $inputsDisabled ? 'disabled' : '' }}></textarea>
                                                @error("basket.{$basketKey}.description")
                                                    <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Right Column: Unit, Qty, Cost -->
                                            <div class="space-y-3">
                                                <div class="grid grid-cols-3 gap-2.5">
                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Unit <span class="text-[#ba1a1a]">*</span></label>
                                                        <input type="text" wire:model.blur="basket.{{ $basketKey }}.unit" x-model="basket['{{ $basketKey }}']['unit']" placeholder="pcs, box, ream" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }}  />
                                                        @error("basket.{$basketKey}.unit")
                                                            <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                        @enderror
                                                    </div>

                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Quantity <span class="text-[#ba1a1a]">*</span></label>
                                                        <input type="number" min="1" wire:model.blur="basket.{{ $basketKey }}.qty" x-model.number="basket['{{ $basketKey }}']['qty']" placeholder="1" class="w-full bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }} />
                                                        @error("basket.{$basketKey}.qty")
                                                            <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                        @enderror
                                                    </div>

                                                    <div>
                                                        <label class="block text-[10px] font-bold uppercase tracking-wider text-[#43474f] mb-1">Est. Unit Cost <span class="text-[#ba1a1a]">*</span></label>
                                                        <div class="relative">
                                                            <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs font-bold text-[#43474f]">₱</span>
                                                            <input type="number" step="0.01" min="0" wire:model.blur="basket.{{ $basketKey }}.unit_cost" x-model.number="basket['{{ $basketKey }}']['unit_cost']" placeholder="0.00" class="w-full pl-5 pr-2 py-2 bg-[#f9f9fe] border border-[#c3c6d1] rounded-lg text-xs font-semibold focus:ring-2 focus:ring-[#001e40] outline-none text-[#001e40]" {{ $inputsDisabled ? 'disabled' : '' }} />
                                                        </div>
                                                        @error("basket.{$basketKey}.unit_cost")
                                                            <p class="text-[10px] text-[#ba1a1a] mt-1">{{ $message }}</p>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="flex justify-between items-center text-[11px] font-bold text-[#43474f] pt-1">
                                                    <span>Subtotal</span>
                                                    <span class="text-[#001e40]" x-text="'₱' + formatPrice((basket['{{ $basketKey }}'] ? basket['{{ $basketKey }}']['qty'] : 0) * (basket['{{ $basketKey }}'] ? basket['{{ $basketKey }}']['unit_cost'] : 0))"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-10 text-xs text-[#43474f]/50 italic">
                                        No line items configured yet. Click "+ Add Item Row" below to add your first item particulars.
                                    </div>
                                @endforelse
                            </div>

                            @if(!$inputsDisabled)
                                <div class="flex justify-start border-t border-[#eeedf2] pt-3">
                                    <button type="button" wire:click="addItemRowToAppLine({{ $selectedAppLineId }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#f9f9fe] hover:bg-[#eeedf2] text-[#001e40] border border-[#c3c6d1] hover:border-[#001e40] text-[11px] font-bold rounded-lg shadow-2xs transition-all">
                                        <span class="material-symbols-outlined text-[15px]">add</span> Add Item Row
                                    </button>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Summary Side card --}}
        <div class="lg:col-span-1 sticky top-[176px] space-y-4">
            <!-- Step 2 PR Categorization & Events Card -->
            <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-2xl p-5 shadow-2xs space-y-4">
                <div class="flex items-center gap-2 border-b border-[#eeedf2] pb-3">
                    <div class="w-8 h-8 rounded-lg bg-[#001e40]/10 flex items-center justify-center text-[#001e40]">
                        <span class="material-symbols-outlined text-[18px]">category</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">PR Categorization & Events</h4>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <!-- Procurement Category Dropdown Element -->
                    <div>
                        <x-form-select 
                            label="Procurement Category" 
                            placeholder="Select Category..." 
                            icon="category" 
                            required
                            wire:model="form.procurementCategory" 
                            :options="[
                                'OFFICE_SUPPLIES' => 'Office Supplies & Stationery',
                                'IT_EQUIPMENT' => 'IT Hardware & Peripherals',
                                'CATERING_EVENTS' => 'Catering, Meals, & Events',
                                'REPAIRS_MAINTENANCE' => 'Vehicle / Building Maintenance',
                                'SERVICES_CONSULTING' => 'General Contractual Services',
                            ]" 
                            :disabled="$inputsDisabled" 
                            :error="$errors->first('form.procurementCategory')"
                        />
                    </div>

                    <!-- Event Scheduling Flag -->
                    <div>
                        <x-form-select 
                            label="Is this request tied to an event?" 
                            placeholder="Select Yes/No..." 
                            icon="event" 
                            required
                            wire:model.live="form.isTiedToEvent" 
                            :options="[
                                '1' => 'Yes, scheduled event',
                                '0' => 'No, regular purchase'
                            ]" 
                            :disabled="$inputsDisabled" 
                            :error="$errors->first('form.isTiedToEvent')"
                        />
                    </div>

                    <!-- Conditional Animated Date Field Input Element -->
                    <div class="transition-all duration-200 {{ $form->isTiedToEvent ? 'opacity-100 scale-100' : 'opacity-40 pointer-events-none' }}">
                        <x-form-input 
                            type="date"
                            label="Date of Event"
                            icon="calendar_month"
                            :required="$form->isTiedToEvent"
                            wire:model="form.eventDate"
                            :disabled="!$form->isTiedToEvent || $inputsDisabled"
                            :error="$errors->first('form.eventDate')"
                        />
                    </div>
                </div>
            </div>

            <!-- PR Summary Card -->
            <div class="bg-[#f9f9fe] border border-[#eeedf2] rounded-2xl p-6 flex flex-col justify-between">
                <div class="flex flex-col gap-6">
                    <div class="flex items-center gap-2 border-b border-[#eeedf2] pb-3">
                        <div class="w-8 h-8 rounded-lg bg-[#001e40]/10 flex items-center justify-center text-[#001e40]">
                            <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider">PR Summary</h4>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @if($this->selectedAppLine)
                            <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm space-y-2">
                                <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">Funding APP Line Source</span>
                                <p class="text-xs font-bold text-[#001e40] leading-snug">{{ $this->selectedAppLine->description }}</p>
                                <p class="text-[10px] text-[#43474f]/70 truncate" title="{{ $this->selectedAppLine->project_title }}">{{ $this->selectedAppLine->project_title }}</p>
                                <div class="pt-2 border-t border-[#eeedf2] flex justify-between items-center text-[10px]">
                                    <span class="font-bold text-[#43474f]/60 uppercase">Available Budget:</span>
                                    <span class="font-bold text-green-700">₱{{ number_format($this->selectedAppLine->approved_budget - $this->selectedAppLine->utilized_budget, 2) }}</span>
                                </div>
                            </div>
                        @endif
                        <div class="bg-white border border-[#eeedf2] rounded-xl p-4 shadow-sm">
                            <span class="text-[10px] font-bold text-[#43474f] uppercase tracking-wider block">PR Estimated Value</span>
                            <span class="text-2xl font-bold block mt-1 transition-colors duration-200"
                                  :class="totalValue > availableBudget ? 'text-red-600' : 'text-green-700'"
                                  x-text="'₱' + formatPrice(totalValue)"></span>
                        </div>

                        <template x-if="totalValue > availableBudget">
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex gap-3 text-red-800 transition-all">
                                <span class="material-symbols-outlined text-[20px] text-red-600 shrink-0 mt-0.5">warning</span>
                                <div class="space-y-1">
                                    <h5 class="text-xs font-bold uppercase tracking-wider text-red-900">Budget Limit Exceeded</h5>
                                    <p class="text-[11px] leading-relaxed font-semibold">
                                        Combined items cost (<span x-text="'₱' + formatPrice(totalValue)"></span>) under {{ $this->selectedAppLine?->project_title ?? 'Selected APP Line' }} exceeds available budget of <span x-text="'₱' + formatPrice(availableBudget)"></span>.
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Buttons --}}
    <div class="border-t border-[#eeedf2] pt-6 flex justify-between items-center">
        <button wire:click="prevStep" class="px-5 py-2.5 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Catalog
        </button>
        <button wire:click="nextStep" class="px-6 py-2.5 bg-[#001e40] text-white font-bold text-sm rounded-xl hover:bg-[#1f3f66] active:scale-95 transition-all flex items-center gap-2 shadow-md">
            Next: Review & Submit <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
    </div>
</div>
