{{-- Employee Table Card --}}
<div class="bg-white border border-[#c3c6d1] rounded-xl shadow-sm overflow-hidden flex flex-col relative">

    <div wire:loading wire:target="search, perPage" class="absolute inset-x-0 bottom-0 top-[57px] bg-white/60 backdrop-blur-[2px] z-50 flex items-center justify-center transition-all">
        <div class="flex flex-col items-center gap-2">
            <div class="w-10 h-10 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
            <span class="text-[12px] font-bold text-[#001e40] uppercase tracking-widest">Updating View...</span>
        </div>
    </div>

    {{-- Table Header --}}
    <div class="p-gutter border-b border-[#c3c6d1] bg-[#f9f9fe] flex flex-wrap justify-between items-center gap-4">
        <h3 class="font-h2 text-h2 text-[#001e40]">Employee Matrix Registry</h3>
        <div class="flex items-center gap-3">
            <div class="relative">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by name, ID, office..."
                       class="pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] focus:border-[#001e40] outline-none transition-all w-64 placeholder-[#43474f]/40"/>
            </div>
            
            <button wire:click="openBulkModal" class="flex items-center gap-2 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 text-indigo-700 px-4 py-2.5 rounded-lg text-sm font-bold transition-all">
                <span class="material-symbols-outlined text-[20px]">group_add</span>
                Bulk Import
            </button>

            <button wire:click="openCreateModal" class="flex items-center gap-2 bg-[#001e40] hover:bg-[#003272] text-white px-4 py-2.5 rounded-lg text-sm font-bold shadow-sm transition-all">
                <span class="material-symbols-outlined text-[20px]">person_add</span>
                New Employee
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto custom-scrollbar">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-[#eeedf2] border-b border-[#c3c6d1]">
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">ID Number</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Employee Name</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Designation</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Office Division</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Sub-Office</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider">Status</th>
                    <th class="p-table-cell-padding text-[12px] font-bold text-[#001e40] uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#c3c6d1] text-[13px]">
                @forelse ($employees as $emp)
                    @php
                        $statusColors = [
                            'PERMANENT' => 'bg-green-50 text-green-700 border border-green-200/50',
                            'CASUAL'    => 'bg-indigo-50 text-indigo-700 border border-indigo-200/50',
                            'JO'        => 'bg-amber-50 text-amber-700 border border-amber-200/50',
                        ];
                        $statusClass = $statusColors[$emp->employment_status] ?? 'bg-gray-50 text-gray-700 border border-gray-200/50';
                    @endphp
                    <tr class="hover:bg-[#f4f3f8] transition-colors group">
                        {{-- ID Number --}}
                        <td class="p-table-cell-padding font-mono font-bold text-[#1a1c1f] tracking-wide">
                            {{ $emp->id_number ?: '—' }}
                        </td>
                        {{-- Name --}}
                        <td class="p-table-cell-padding font-bold text-[#001e40]">
                            {{ $emp->fullname }}
                        </td>
                        {{-- Designation --}}
                        <td class="p-table-cell-padding text-[#43474f]">
                            {{ $emp->designation }} <span class="text-[10px] bg-[#eeedf2] px-1.5 py-0.5 rounded text-[#1a1c1f]">SG {{ $emp->salary_grade }}</span>
                        </td>
                        {{-- Office --}}
                        <td class="p-table-cell-padding font-bold text-[#001b3c]">
                            {{ $emp->office_division }}
                        </td>
                        {{-- Sub-Office --}}
                        <td class="p-table-cell-padding text-[#43474f] italic">
                            {{ $emp->sub_office ?: '—' }}
                        </td>
                        {{-- Status --}}
                        <td class="p-table-cell-padding">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase {{ $statusClass }}">
                                {{ $emp->employment_status }}
                            </span>
                        </td>
                        {{-- Actions --}}
                        <td class="p-table-cell-padding text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button wire:click="openEditModal({{ $emp->id }})" class="p-1 text-[#43474f] hover:text-[#001e40] hover:bg-[#eeedf2] rounded-lg transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </button>
                                <button wire:click="confirmDelete({{ $emp->id }})" class="p-1 text-[#ba1a1a] hover:bg-[#ffdad6] rounded-lg transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-[#43474f] italic">
                            No employees matched the search filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination Bar --}}
    @if ($employees->hasPages())
        <div class="p-gutter border-t border-[#c3c6d1] bg-[#f9f9fe] flex justify-between items-center employee-pagination">
            <div class="text-xs text-[#43474f] font-bold">
                Showing {{ $employees->firstItem() }} to {{ $employees->lastItem() }} of {{ $employees->total() }} results
            </div>
            {{ $employees->links() }}
        </div>
        <style>
            .employee-pagination nav { display: inline-flex; gap: 0.25rem; }
            .employee-pagination nav span, 
            .employee-pagination nav a {
                padding: 0.5rem 0.75rem;
                font-size: 0.75rem;
                font-weight: 700;
                border-radius: 0.375rem;
                border: 1px solid #c3c6d1;
                background-color: #fff;
                color: #43474f;
                transition: all 0.15s ease;
            }
            .employee-pagination nav span[aria-current="page"] {
                background-color: #001e40;
                border-color: #001e40;
                color: #fff;
            }
            .employee-pagination nav a:hover {
                background-color: #f4f3f8;
                border-color: #001e40;
                color: #001e40;
            }
            .employee-pagination nav svg { width: 1.25rem; height: 1.25rem; }
        </style>
    @endif
</div>
