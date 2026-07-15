            {{-- PR Table --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
                <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
                    </div>
                </div>

                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <h3 class="font-bold text-[#001e40] text-lg">Office PR Registry</h3>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search PR or purpose..." class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                        </div>
                        <x-primary-button variant="secondary" icon="filter_list" class="!px-3" />
                        <x-primary-button variant="secondary" icon="download" class="!px-3" />
                    </div>
                </div>
                <div class="overflow-x-auto lg:overflow-visible custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Tracking Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">PR Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Requested</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Purpose</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                           @forelse($folders as $folder)
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#43474f] font-mono text-xs">{{ $folder->tracking_number }}</td>
                                <td class="p-table-cell-padding font-bold text-[#001e40]">{{ $folder->pr_number ?? '—' }}</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->created_at->format('M d, Y') }}</td>
                                @php
                                    $purposeLines = explode("\n", trim($folder->overall_purpose ?: 'No purpose specified'));
                                    $firstLine = trim($purposeLines[0] ?? '');
                                    $hasMultipleLines = count($purposeLines) > 1 || strlen($firstLine) > 40;
                                    $displayPurpose = Str::limit($firstLine, 40) . ($hasMultipleLines ? '...' : '');
                                @endphp
                                <td class="p-table-cell-padding text-[#1a1c1f] relative {{ $hasMultipleLines ? 'group' : '' }}">
                                    <div class="{{ $hasMultipleLines ? 'cursor-help font-medium hover:text-[#001e40] transition-colors' : 'font-medium text-[#43474f]' }}">
                                        {{ $displayPurpose }}
                                    </div>
                                    @if($hasMultipleLines)
                                        <!-- Custom Hover Tooltip (Scrollable) -->
                                        <div class="absolute left-0 top-full mt-1 hidden group-hover:block w-80 max-h-32 overflow-y-auto bg-white text-[#43474f] text-[12px] leading-relaxed p-3 rounded-lg shadow-lg border border-[#c3c6d1] z-50 whitespace-pre-line custom-scrollbar">{{ trim($folder->overall_purpose) ?: 'No purpose specified' }}</div>
                                    @endif
                                </td>
                                <td class="p-table-cell-padding">
                                    @php
                                        $statusColors = [
                                            'DRAFT' => 'bg-[#eeedf2] text-[#43474f]',
                                            'SUBMITTED_TO_GSU' => 'bg-[#e0f7fa] text-[#006064] border border-[#00acc1]/20',
                                            'ROUTING' => 'bg-[#fff9c4] text-[#f57f17] border border-[#fbc02d]/30',
                                            'APPROVED' => 'bg-green-50 text-green-800 border border-green-200',
                                            'PR_PRINTED' => 'bg-[#ffdbca] text-[#341100]',
                                            'RFQ_SENT' => 'bg-[#d8e1ea] text-[#5b646b]',
                                            'AWARDED' => 'bg-green-100 text-green-800',
                                            'PO_RELEASED' => 'bg-[#d5e3ff] text-[#001b3c]',
                                            'CANCELLED' => 'bg-red-50 text-red-700 border border-red-200',
                                            'CANCELLED_BY_USER' => 'bg-red-50 text-red-700 border border-red-200',
                                            'RETURNED_FOR_EDIT' => 'bg-amber-50 text-amber-800 border border-amber-200',
                                            'RETURNED_FOR_COMPLIANCE' => 'bg-purple-50 text-purple-800 border border-purple-200',
                                        ];
                                        $color = $statusColors[$folder->status] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase {{ $color }}">{{ $folder->status_label }}</span>
                                </td>

                                <td class="p-table-cell-padding text-right">
                                        <div class="relative inline-block text-left" x-data="{ open: false, coords: { top: 0, left: 0 } }">
                                            <button @click="open = !open; if(open) { let rect = $el.getBoundingClientRect(); coords.top = rect.bottom + window.scrollY; coords.left = rect.right - 240 + window.scrollX; }" class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all flex items-center justify-center" title="Actions">
                                                <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                            </button>
                                            
                                            <template x-teleport="body">
                                                <div x-show="open"
                                                     @click.outside="open = false"
                                                     x-transition:enter="transition ease-out duration-200"
                                                     x-transition:enter-start="opacity-0 scale-95"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="opacity-100 scale-100"
                                                     x-transition:leave-end="opacity-0 scale-95"
                                                     class="absolute z-[99999] w-60 rounded-xl shadow-lg bg-white border border-[#c3c6d1]"
                                                     :style="`top: ${coords.top}px; left: ${coords.left}px; display: none;`"
                                                     @click="open = false">
                                                    <div class="p-1.5 space-y-1 text-left">
                                                        {{-- View PR Details (Always Available) --}}
                                                        <button wire:click="viewDetails('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">info</span>
                                                            <span>View PR Details</span>
                                                        </button>

                                                        {{-- View PDF Form (Only if not Draft or Cancelled) --}}
                                                        @if(!in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER']))
                                                            <button wire:click="generateAndViewPdf('{{ $folder->id }}')" wire:loading.attr="disabled" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all relative whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                                <span>View PDF Form</span>
                                                            </button>
                                                        @endif

                                                        {{-- Audit Log History (Always Available) --}}
                                                        <button wire:click="viewHistory('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">history</span>
                                                            <span>View Audit Trail</span>
                                                        </button>

                                                        @if($folder->status === 'DRAFT')
                                                            <div class="h-px bg-[#eeedf2] my-1"></div>
                                                            {{-- Route for Signature --}}
                                                            <a href="{{ route('procurement.review', $folder->id) }}" wire:navigate class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#1f477b] hover:bg-blue-50 rounded-lg transition-all whitespace-nowrap">
                                                                 <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                                                 <span>Sign & Submit to GSU</span>
                                                            </a>

                                                            {{-- Edit Draft --}}
                                                            <button wire:click="editPr('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#001e40] hover:bg-[#f4f3f8] hover:text-black rounded-lg transition-all whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                                                <span>Edit Draft</span>
                                                            </button>

                                                            {{-- Delete Draft --}}
                                                            <button wire:click="confirmDelete('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#ba1a1a] hover:bg-red-50 rounded-lg transition-all whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                                                <span>Delete Draft</span>
                                                            </button>
                                                        @endif


                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                        <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">receipt_long</span>
                                        @if($search)
                                            <p class="font-bold text-[#001e40] text-lg">No Results Found</p>
                                            <p class="text-[13px] text-[#43474f] max-w-xs">We couldn't find any procurement folders matching "{{ $search }}".</p>
                                        @else
                                            <p class="font-bold text-[#001e40] text-lg">No Purchase Requests Found</p>
                                            <p class="text-[13px] text-[#43474f] max-w-xs">There are no procurement requests yet. Create the first one to start tracking your purchasing pipeline.</p>
                                            @if($appGateCleared)
                                                <x-primary-button icon="add" class="mt-2" x-on:click="$dispatch('open-new-pr')">Create First PR</x-primary-button>
                                            @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($folders->hasPages())
                    <div class="p-gutter border-t border-[#c3c6d1] bg-[#f9f9fe]">
                        {{ $folders->links() }}
                    </div>
                @elseif($folders->count() > 0)
                    <div class="p-gutter border-t border-[#c3c6d1] flex items-center justify-between bg-[#f9f9fe]">
                        <p class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Showing 1 to {{ $folders->count() }} of {{ $folders->total() }} PRs</p>
                    </div>
                @endif
            </div>
        @else
            {{-- GSU Inbox Table --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative">
                <div wire:loading class="absolute inset-x-0 bottom-0 top-[45px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating Inbox...</span>
                    </div>
                </div>

                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-[#001e40] text-lg">GSU Inbox</h3>
                        <p class="text-[11px] text-[#43474f] mt-0.5">Incoming Purchase Requests submitted by end-users. Review and assign official PR numbers before routing.</p>
                    </div>
                </div>

                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Tracking Number</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Date Submitted</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Requesting Unit</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Compiler</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider">Total Cost</th>
                                <th class="p-table-cell-padding text-[11px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                            @forelse($triageFolders as $folder)
                            <tr class="hover:bg-[#f4f3f8] transition-colors">
                                <td class="p-table-cell-padding font-bold text-[#001e40]">
                                    <div>{{ $folder->tracking_number ?? '—' }}</div>
                                    @if($this->checkIsPossibleDuplicate($folder))
                                        <span class="inline-flex items-center gap-1 bg-[#fff3cd] text-[#856404] border border-[#ffeeba] px-1.5 py-0.5 rounded text-[10px] font-bold mt-1">
                                            <span class="material-symbols-outlined text-[12px] font-bold">warning</span> Potential Duplicate
                                        </span>
                                    @endif
                                </td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">{{ $folder->created_at->format('M d, Y') }}</td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">
                                    {{ $folder->requesting_unit ?? $folder->overall_purpose ?? '—' }}
                                </td>
                                <td class="p-table-cell-padding text-[#1a1c1f]">
                                    {{ $folder->requestedBy?->fullname ?? '—' }}
                                </td>
                                <td class="p-table-cell-padding font-bold text-[#001e40]">
                                    ₱{{ number_format($folder->prItems->sum('estimated_total_cost'), 2) }}
                                </td>
                                <td class="p-table-cell-padding text-right">
                                    <div class="flex justify-end items-center gap-2">
                                        <a href="{{ route('procurement.gsu.review', $folder->id) }}"
                                            class="px-3 py-1.5 bg-[#f4f3f8] text-[#001e40] border border-[#c3c6d1] text-[11px] font-bold rounded-lg hover:bg-[#eeedf2] transition-all flex items-center gap-1.5"
                                        >
                                            <span class="material-symbols-outlined text-[14px]">rate_review</span>
                                            Open & Review
                                        </a>

                                        <button
                                            wire:click="viewHistory('{{ $folder->id }}')"
                                            class="p-1.5 bg-[#f4f3f8] text-[#43474f] border border-[#c3c6d1] rounded-lg hover:bg-[#eeedf2] transition-all material-symbols-outlined text-[18px]"
                                            title="Audit Trail"
                                        >history</button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                        <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">inbox</span>
                                        <p class="font-bold text-[#001e40] text-lg">Inbox is Clear</p>
                                        <p class="text-[13px] text-[#43474f] max-w-xs">There are no pending incoming Purchase Requests currently awaiting GSU review.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Insight Cards (Hidden on Empty State) --}}
        @if($totalActive > 0 || $totalPending > 0)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">
            <div class="bg-[#d5e3ff]/30 p-8 border border-[#001e40]/10 rounded-2xl relative overflow-hidden group shadow-sm">
                <div class="relative z-10">
                    <h4 class="text-xl font-bold text-[#001e40] mb-2">Delivery Bottlenecks Detected</h4>
                    <p class="text-sm text-[#43474f] max-w-md leading-relaxed">3 awarded purchase requests from "Global Tech Solutions Inc." are exceeding the expected delivery timeline. Consideration for alternative RFQs recommended.</p>
                    <x-primary-button class="mt-5">Resolve Delays</x-primary-button>
                </div>
                <span class="material-symbols-outlined absolute -right-6 -bottom-6 text-[140px] text-[#001e40]/5 group-hover:scale-110 transition-transform duration-700" style="font-variation-settings: 'FILL' 1;">local_shipping</span>
            </div>
            <div class="bg-white p-8 border border-[#c3c6d1] rounded-2xl shadow-sm flex gap-6 items-start">
                <div class="w-24 h-24 rounded-2xl bg-[#eeedf2] flex items-center justify-center flex-shrink-0 shadow-inner">
                    <span class="material-symbols-outlined text-[48px] text-[#43474f]">inventory</span>
                </div>
                <div class="flex-1">
                    <div class="flex justify-between items-start mb-3">
                        <h4 class="text-xl font-bold text-[#1a1c1f]">Regional Stock Utilization</h4>
                        <span class="px-3 py-1 bg-[#ffdad6] text-[#93000a] text-[10px] font-bold rounded-full uppercase">LOW STOCK</span>
                    </div>
                    <p class="text-sm text-[#43474f] mb-5 leading-relaxed">Critical medical supplies in Warehouse Sector B are below the 15% threshold. Initiate procurement cycle for FY26 Q2.</p>
                    <div class="w-full h-2 bg-[#eeedf2] rounded-full overflow-hidden">
                        <div class="h-full bg-[#ba1a1a] rounded-full" style="width: 15%"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        </div> {{-- Close x-show="!isCreatingPr" --}}
