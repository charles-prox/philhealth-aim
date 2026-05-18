{{-- Page Header --}}
<div class="mb-8">
    <h1 class="text-2xl font-bold text-[#001e40]">Realignment Wizard</h1>
    <p class="text-sm text-[#43474f] mt-1">Move funds from one or more source lines to a target budget line. A new DRAFT version will be created for review and approval.</p>
</div>

@if(session('cob_status'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-800 text-sm flex items-center gap-3">
        <span class="material-symbols-outlined text-[20px] text-green-700" style="font-variation-settings:'FILL' 1;">check_circle</span>
        {{ session('cob_status') }}
    </div>
@endif

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">

    {{-- LEFT COLUMN: Source → Target Form --}}
    <div class="xl:col-span-2 space-y-6">

        {{-- Active version info banner --}}
        @if($this->activeVersion)
            <div class="p-4 bg-[#f4f3f8] border border-[#c3c6d1] rounded-xl flex items-center gap-3">
                <span class="material-symbols-outlined text-[20px] text-[#001e40]" style="font-variation-settings:'FILL' 1;">account_balance</span>
                <div>
                    <p class="text-xs font-bold text-[#43474f] uppercase tracking-wider">Active Budget Version</p>
                    <p class="text-sm font-bold text-[#001e40]">{{ $this->activeVersion->version_name }}</p>
                </div>
            </div>
        @else
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">warning</span>
                No approved COB version found for an open fiscal year. Please approve a version in the Annual Kick-off first.
            </div>
        @endif

        {{-- 2. Target Budget Line --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-bold text-[#001e40] uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">flag</span>Target Budget Line
                <span class="ml-auto text-[10px] text-[#43474f] font-normal normal-case tracking-normal">Funds will be added here</span>
            </h2>

            {{-- Toggle --}}
            <div class="flex gap-3 mb-5">
                <button wire:click="$set('targetMode', 'existing')"
                        class="flex-1 py-2.5 px-4 text-sm font-bold rounded-lg border-2 transition-all {{ $targetMode === 'existing' ? 'border-[#001e40] bg-[#001e40] text-white' : 'border-[#c3c6d1] text-[#43474f] hover:border-[#001e40]' }}">
                    Existing Budget Line
                </button>
                <button wire:click="$set('targetMode', 'new')"
                        class="flex-1 py-2.5 px-4 text-sm font-bold rounded-lg border-2 transition-all {{ $targetMode === 'new' ? 'border-[#001e40] bg-[#001e40] text-white' : 'border-[#c3c6d1] text-[#43474f] hover:border-[#001e40]' }}">
                    + Create New Line
                </button>
            </div>

            @if($targetMode === 'existing')
                <x-form-select wire:model.live="targetItemId" :options="$this->availableItems" label="Select Target Item" icon="search" searchable />
                @if($this->targetItem)
                    <div class="mt-3 p-3 bg-[#f4f3f8] rounded-lg border border-[#c3c6d1]/50 text-xs space-y-1">
                        <p class="font-bold text-[#001e40]">{{ $this->targetItem->ppa_code }} – {{ $this->targetItem->ppa_desc }}</p>
                        <p class="text-[#43474f]">{{ $this->targetItem->full_particulars }}</p>
                        <p class="text-[#43474f] font-bold">Current Balance: <span class="text-green-700">₱{{ number_format($this->targetItem->current_balance, 2) }}</span></p>
                    </div>
                @endif
            @else
                {{-- New item inline sub-form --}}
                <div class="grid grid-cols-2 gap-4 pt-2">
                    <div class="col-span-2 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-xs flex items-start gap-2">
                        <span class="material-symbols-outlined text-[16px] mt-0.5" style="font-variation-settings:'FILL' 1;">info</span>
                        Fill all classification fields accurately. Missing fields (Account, Tier, Class, Sector) will cause reporting gaps in future RSMI runs.
                    </div>
                    <div class="col-span-1"><x-form-input label="PPA Code *" wire:model="newPpaCode" placeholder="e.g. 100-001" /></div>
                    <div class="col-span-1"><x-form-input label="Account Code" wire:model="newAccount" placeholder="e.g. 5-02-99-990" /></div>
                    <div class="col-span-2"><x-form-input label="PPA Description *" wire:model="newPpaDesc" placeholder="Program / Project / Activity" /></div>
                    <div class="col-span-1"><x-form-input label="Sub PPA Code" wire:model="newSubPpaCode" /></div>
                    <div class="col-span-1"><x-form-input label="Sub PPA Description" wire:model="newSubPpaDesc" /></div>
                    <div class="col-span-2"><x-form-input label="Full Particulars *" wire:model="newFullParticulars" placeholder="Detailed item description" /></div>
                    <div class="col-span-1"><x-form-input label="Expense Description" wire:model="newExpDesc" placeholder="e.g. Office Supplies" /></div>
                    <div class="col-span-1"><x-form-input label="Unit" wire:model="newUnit" placeholder="Lot, Unit, Piece..." /></div>
                    <div class="col-span-1">
                        <x-form-select label="Tier" wire:model="newTier" :options="['1' => 'Tier 1', '2' => 'Tier 2']" />
                    </div>
                    <div class="col-span-1">
                        <x-form-select label="Class" wire:model="newClass" :options="['MOOE' => 'MOOE', 'CO' => 'CO', 'PS' => 'PS']" />
                    </div>
                    <div class="col-span-1"><x-form-input label="GASS" wire:model="newGass" /></div>
                    <div class="col-span-1"><x-form-input label="Sector" wire:model="newSector" /></div>
                    <div class="col-span-1"><x-form-input label="Office ID" wire:model="newOfficeId" /></div>
                    <div class="col-span-1"><x-form-input label="Transaction ID" wire:model="newTransactionId" placeholder="Auto-generated if blank" /></div>
                    <div class="col-span-2"><x-form-input label="WFP ID" wire:model="newWorkAndFinancialPlanId" /></div>
                    <div class="col-span-2 flex items-center gap-3">
                        <input type="checkbox" wire:model="newIsIct" id="newIsIct" class="w-4 h-4 text-[#001e40] rounded border-[#c3c6d1]">
                        <label for="newIsIct" class="text-sm text-[#43474f] font-bold">Flag as ICT item</label>
                    </div>
                </div>
            @endif
        </div>

        {{-- 3. Source Items --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-bold text-[#001e40] uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">remove_circle</span>Source Budget Lines
                <span class="ml-auto text-[10px] text-[#43474f] font-normal normal-case tracking-normal">Funds will be deducted here</span>
            </h2>

            @error('sources')
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">error</span> {{ $message }}
                </div>
            @enderror

            @if(count($sources) === 0)
                <div class="py-8 text-center border-2 border-dashed border-[#c3c6d1] rounded-xl">
                    <span class="material-symbols-outlined text-[40px] text-[#c3c6d1]">playlist_add</span>
                    <p class="text-sm text-[#43474f] mt-2">No sources selected. Add items below to begin.</p>
                </div>
            @else
                <div class="space-y-3 mb-4">
                    @foreach($sources as $i => $source)
                        @php $srcItem = \App\Models\CobItem::find($source['item_id']); @endphp
                        <div class="flex items-center gap-4 p-3 bg-[#f4f3f8] border border-[#c3c6d1]/50 rounded-xl">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-[#001e40] truncate">{{ $srcItem?->ppa_code }} – {{ \Illuminate\Support\Str::limit($srcItem?->full_particulars ?? $srcItem?->ppa_desc, 50) }}</p>
                                <p class="text-[10px] text-[#43474f]">Available: ₱{{ number_format($srcItem?->current_balance ?? 0, 2) }}</p>
                            </div>
                            <div class="w-40 flex-shrink-0">
                                <input type="number" wire:model.live="sources.{{ $i }}.amount"
                                       min="0.01" max="{{ $srcItem?->current_balance }}" step="0.01"
                                       class="w-full py-2.5 px-3 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] text-right"
                                       placeholder="0.00" />
                            </div>
                            <x-icon-button icon="close" variant="tertiary" wire:click="removeSource({{ $i }})" />
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Add Source --}}
            @if($showAddSource)
                <div class="mt-4 p-4 border border-[#001e40]/20 bg-[#f9f9fe] rounded-xl space-y-3">
                    <x-form-select wire:model="addSourceItemId" label="Select Source Item" :options="$this->availableSources->mapWithKeys(fn($i) => [$i->id => '[' . $i->ppa_code . '] ' . \Illuminate\Support\Str::limit($i->full_particulars ?? $i->ppa_desc, 55)])->toArray()" searchable />
                    <div class="flex gap-2">
                        <x-primary-button wire:click="addSource" class="flex-1">Add to Sources</x-primary-button>
                        <x-primary-button variant="secondary" wire:click="$set('showAddSource', false)">Cancel</x-primary-button>
                    </div>
                </div>
            @else
                <button wire:click="$set('showAddSource', true)"
                        class="mt-4 w-full flex items-center justify-center gap-2 py-2.5 border-2 border-dashed border-[#001e40]/30 text-[#001e40] font-bold text-sm rounded-xl hover:border-[#001e40] hover:bg-[#f4f3f8] transition-all">
                    <span class="material-symbols-outlined text-[18px]">add</span>Add Source Item
                </button>
            @endif
        </div>

        {{-- 4. Memo & Remarks --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-bold text-[#001e40] uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">description</span>Documentation
            </h2>
            <div class="space-y-4">
                <x-form-input label="Reference Memo No. *" wire:model="referenceMemo" icon="tag" placeholder="e.g. GSU-2026-MEMO-001" />
                <div class="space-y-1">
                    <label class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider ml-1">Justification / Remarks</label>
                    <textarea wire:model="remarks"
                              class="w-full bg-white border border-[#c3c6d1] rounded-xl p-4 text-sm focus:ring-[#001e40] focus:border-[#001e40] min-h-[100px]"
                              placeholder="Explain the reason for this realignment..."></textarea>
                </div>
                <div class="space-y-2">
                    <label class="text-[11px] font-bold text-[#43474f] uppercase tracking-wider ml-1">Signed Memo PDF (optional)</label>
                    <label class="flex items-center gap-3 p-3 border border-[#c3c6d1] rounded-xl bg-[#f4f3f8] cursor-pointer hover:border-[#001e40] transition-all">
                        <span class="material-symbols-outlined text-[24px] text-[#001e40]">upload_file</span>
                        <div class="flex-1 text-sm text-[#43474f]">
                            @if($memoAttachment)
                                <span class="font-bold text-[#001e40]">{{ $memoAttachment->getClientOriginalName() }}</span>
                            @else
                                <span>Click to upload signed memo PDF (max 10MB)</span>
                            @endif
                        </div>
                        <input type="file" wire:model="memoAttachment" accept=".pdf" class="hidden" />
                    </label>
                </div>
            </div>
        </div>

    </div>

    {{-- RIGHT COLUMN: Balance Summary + Realignment Versions --}}
    <div class="xl:col-span-1 space-y-4 sticky top-6">
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm p-6">
            <h2 class="text-sm font-bold text-[#001e40] uppercase tracking-wider mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">balance</span>Realignment Check
            </h2>

            @php
                $total = $this->totalReductions;
                $balanced = $total > 0;
            @endphp

            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-[#eeedf2]">
                    <span class="text-sm text-[#43474f]">Total Deductions</span>
                    <span class="font-bold text-red-700 text-base">– ₱{{ number_format($total, 2) }}</span>
                </div>
                <div class="flex items-center justify-between py-3 border-b border-[#eeedf2]">
                    <span class="text-sm text-[#43474f]">Target Increase</span>
                    <span class="font-bold text-green-700 text-base">+ ₱{{ number_format($total, 2) }}</span>
                </div>
                <div class="flex items-center justify-between py-3">
                    <span class="text-sm font-bold text-[#001e40]">Net Change</span>
                    <span class="font-bold text-lg {{ $total > 0 ? 'text-green-700' : 'text-[#c3c6d1]' }}">₱ 0.00</span>
                </div>

                {{-- Balance Bar --}}
                <div class="rounded-xl overflow-hidden h-3 bg-[#f4f3f8] border border-[#c3c6d1]">
                    @if($balanced)
                        <div class="h-full bg-gradient-to-r from-[#001e40] to-green-500 transition-all duration-500" style="width: 100%"></div>
                    @endif
                </div>
                <p class="text-center text-[11px] font-bold uppercase tracking-wider {{ $balanced ? 'text-green-700' : 'text-[#c3c6d1]' }}">
                    {{ $balanced ? '✓ Balanced — Zero Net Change' : 'Add sources to balance' }}
                </p>

                @if(count($sources) > 0)
                    <div class="space-y-2 pt-2">
                        @foreach($sources as $s)
                            @php $si = \App\Models\CobItem::find($s['item_id']); @endphp
                            <div class="flex items-center justify-between text-xs text-[#43474f]">
                                <span class="truncate max-w-[140px]">{{ $si?->ppa_code }}</span>
                                <span class="font-bold text-red-600">– ₱{{ number_format($s['amount'] ?? 0, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Submit --}}
        <x-primary-button wire:click="processRealignment"
                          wire:loading.attr="disabled"
                          wire:target="processRealignment"
                          class="w-full justify-center text-base py-3">
            <span class="flex items-center justify-center gap-2 whitespace-nowrap">
                <span wire:loading.remove wire:target="processRealignment" class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">swap_horiz</span>
                    Submit Realignment
                </span>
                <span wire:loading wire:target="processRealignment" class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Processing...
                </span>
            </span>
        </x-primary-button>
        <p class="text-center text-[11px] text-[#43474f]">A new <strong>DRAFT</strong> version will be created for review. The active budget remains unchanged until approved.</p>

        {{-- Realignment Versions List --}}
        <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-[#eeedf2] flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-[#001e40]">history</span>
                <h2 class="text-sm font-bold text-[#001e40] uppercase tracking-wider">Realignment Versions</h2>
            </div>

            @if($this->realignmentVersions->isEmpty())
                <div class="py-10 text-center flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-[36px] text-[#c3c6d1]">swap_horiz</span>
                    <p class="text-xs text-[#43474f] font-bold">No realignment drafts yet</p>
                </div>
            @else
                <div class="divide-y divide-[#eeedf2]">
                    @foreach($this->realignmentVersions as $rv)
                        <div class="p-4 hover:bg-[#f9f9fe] transition-colors">
                            <div class="flex items-center gap-2 mb-1">
                                @if($rv->status === 'APPROVED')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#d5e3ff] text-[#001b3c]">Approved</span>
                                @elseif($rv->status === 'SUPERSEDED')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-[#ffdbca] text-[#723610]">Superseded</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800">Draft</span>
                                @endif
                            </div>
                            <p class="text-xs font-bold text-[#001e40] leading-snug">{{ $rv->version_name }}</p>
                            @if($rv->remarks)
                                <p class="text-[10px] text-[#43474f] mt-1 italic">{{ \Illuminate\Support\Str::limit($rv->remarks, 60) }}</p>
                            @endif
                            <div class="flex items-center gap-2 mt-3">
                                @if($rv->status === 'DRAFT')
                                    <button @click="$dispatch('confirm', {
                                                title: 'Approve Realignment?',
                                                message: 'This will supersede the current active version. Proceed?',
                                                type: 'success',
                                                onConfirm: () => $wire.activateRealignment('{{ $rv->id }}')
                                            })"
                                            class="flex items-center gap-1.5 px-3 py-1.5 bg-green-700 text-white font-bold text-[11px] rounded-lg hover:bg-green-800 transition-all">
                                        <span class="material-symbols-outlined text-[14px]">check_circle</span>Approve
                                    </button>
                                    <button @click="$dispatch('confirm', {
                                                title: 'Delete Draft?',
                                                message: 'This will permanently remove this realignment draft.',
                                                type: 'danger',
                                                onConfirm: () => $wire.deleteRealignment('{{ $rv->id }}')
                                            })"
                                            class="flex items-center gap-1.5 px-3 py-1.5 bg-red-700 text-white font-bold text-[11px] rounded-lg hover:bg-red-800 transition-all">
                                        <span class="material-symbols-outlined text-[14px]">delete</span>Delete
                                    </button>
                                @endif
                                <a href="{{ route('cob.items', $rv->id) }}"
                                   class="flex items-center gap-1.5 px-3 py-1.5 border border-[#c3c6d1] text-[#43474f] font-bold text-[11px] rounded-lg hover:border-[#001e40] hover:text-[#001e40] transition-all ml-auto">
                                    <span class="material-symbols-outlined text-[14px]">open_in_new</span>View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
