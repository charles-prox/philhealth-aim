            {{-- Main Purchase Request Table Card --}}
            <div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm relative overflow-hidden">
                {{-- Inner Loading Overlay --}}
                <div wire:loading class="absolute inset-0 bg-white/70 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
                    <div class="flex flex-col items-center gap-2">
                        <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                        <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest animate-pulse">Syncing Registry...</span>
                    </div>
                </div>

                {{-- Table Workspace Header --}}
                <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-[#001e40] text-[24px]">view_list</span>
                        <h3 class="font-bold text-[#001e40] text-lg">
                            {{ $isReadOnly ? 'Office Division PR Tracker' : 'Personal PR Tracker' }}
                        </h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                            <input type="text" 
                                   wire:model.live.debounce.300ms="search" 
                                   placeholder="Search PR or purpose..." 
                                   class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all w-56 placeholder-[#43474f]/40"/>
                        </div>
                    </div>
                </div>

                {{-- Registry Table --}}
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
                                            <button @click="open = !open; if(open) { let rect = $el.getBoundingClientRect(); coords.top = rect.bottom + window.scrollY; coords.left = rect.right - 240 + window.scrollX; }" 
                                                    class="p-1.5 hover:bg-[#eeedf2] rounded-lg text-[#43474f] hover:text-[#001e40] transition-all flex items-center justify-center" 
                                                    title="Actions">
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
                                                        {{-- View PR Details --}}
                                                        <button wire:click="viewDetails('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">info</span>
                                                            <span>View PR Details</span>
                                                        </button>

                                                        {{-- View PDF Form --}}
                                                        @if(!in_array($folder->status, ['DRAFT', 'CANCELLED', 'CANCELLED_BY_USER']))
                                                            <button wire:click="generateAndViewPdf('{{ $folder->id }}')" wire:loading.attr="disabled" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all relative whitespace-nowrap">
                                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                                <span>View PDF Form</span>
                                                            </button>
                                                        @endif

                                                        {{-- Audit Log History --}}
                                                        <button wire:click="viewHistory('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] rounded-lg transition-all whitespace-nowrap">
                                                            <span class="material-symbols-outlined text-[18px]">history</span>
                                                            <span>View Audit Trail</span>
                                                        </button>

                                                        @if(!$isReadOnly)
                                                            @if($folder->status === 'SUBMITTED_TO_GSU')
                                                                <div class="h-px bg-[#eeedf2] my-1"></div>
                                                                {{-- Cancel Submission --}}
                                                                <button wire:click="cancelSubmission('{{ $folder->id }}')" class="flex items-center gap-2.5 w-full px-3 py-2 text-xs font-bold text-[#ba1a1a] hover:bg-red-50 rounded-lg transition-all whitespace-nowrap">
                                                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                                                    <span>Cancel Submission</span>
                                                                </button>
                                                            @endif

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
                                                        @endif
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-20 text-center">
                                        <div class="flex flex-col items-center gap-3 text-[#43474f]">
                                            <span class="material-symbols-outlined text-[56px] text-[#c3c6d1]">receipt_long</span>
                                            @if($search)
                                                <p class="font-bold text-[#001e40] text-lg">No Results Found</p>
                                                <p class="text-[13px] text-[#43474f] max-w-xs">We couldn't find any procurement folders matching "{{ $search }}".</p>
                                            @else
                                                <p class="font-bold text-[#001e40] text-lg">No Purchase Requests Found</p>
                                                <p class="text-[13px] text-[#43474f] max-w-xs">There are no procurement requests yet. Create the first one to start tracking your purchasing pipeline.</p>
                                                @if($appGateCleared && !$isReadOnly)
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

                {{-- Pagination / Table Footer --}}
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
        </div>
