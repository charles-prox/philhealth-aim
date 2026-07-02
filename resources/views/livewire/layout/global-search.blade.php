<?php

use Livewire\Volt\Component;
use App\Models\ProcurementFolder;
use App\Models\Employee;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public string $searchQuery = '';

    #[Computed]
    public function folders()
    {
        if (strlen($this->searchQuery) < 2) {
            return collect();
        }

        $query = ProcurementFolder::query();
        $qString = strtolower($this->searchQuery);

        // Security code checking:
        $cleanSearch = strtoupper(str_replace('TRK-', '', trim($this->searchQuery)));
        $matchingId = null;
        if (ctype_xdigit($cleanSearch) && strlen($cleanSearch) === 12) {
            $matchingId = ProcurementFolder::select('id')
                ->get()
                ->first(fn($f) => strtoupper(substr(md5($f->id), 0, 12)) === $cleanSearch)
                ?->id;
        }

        return $query->where(function($q) use ($qString, $matchingId) {
            $q->where(DB::raw('LOWER(pr_number)'), 'like', '%' . $qString . '%')
              ->orWhere(DB::raw('LOWER(tracking_number)'), 'like', '%' . $qString . '%')
              ->orWhere(DB::raw('LOWER(overall_purpose)'), 'like', '%' . $qString . '%')
              ->orWhere(DB::raw('LOWER(requesting_unit)'), 'like', '%' . $qString . '%');

            if ($matchingId) {
                $q->orWhere('id', $matchingId);
            }
        })
        ->with(['requestedBy'])
        ->limit(5)
        ->get();
    }

    #[Computed]
    public function employees()
    {
        if (strlen($this->searchQuery) < 2) {
            return collect();
        }

        return Employee::where(DB::raw('LOWER(fullname)'), 'like', '%' . strtolower($this->searchQuery) . '%')
            ->orWhere(DB::raw('LOWER(designation)'), 'like', '%' . strtolower($this->searchQuery) . '%')
            ->orWhere(DB::raw('LOWER(office_division)'), 'like', '%' . strtolower($this->searchQuery) . '%')
            ->limit(5)
            ->get();
    }

    public function navigateToFolder(string $folderId): void
    {
        $folder = ProcurementFolder::findOrFail($folderId);
        $user = auth()->user();
        $params = ['search' => $folder->pr_number ?: $folder->tracking_number];

        $url = match (true) {
            $user->hasAnyRole(['Admin', 'Procurement Officer']) => route('procurement.admin', $params),
            $user->hasRole('Office Head') => route('procurement.office', $params),
            default => route('procurement.portal', $params),
        };

        $this->dispatch('close-search');
        $this->redirect($url, navigate: true);
    }
}; ?>

<div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-[#c3c6d1] overflow-hidden"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 scale-95 translate-y-[-20px]"
     x-transition:enter-end="opacity-100 scale-100 translate-y-0">

    <div class="p-4 border-b border-[#eeedf2] flex items-center gap-4"
         x-data="{ localQuery: '' }">
        <span class="material-symbols-outlined text-[#001e40] text-[24px]">search</span>
        {{-- wire:ignore: prevent Livewire from morphing this input on re-renders --}}
        <input type="text"
               wire:ignore
               x-model="localQuery"
               @input.debounce.300ms="$wire.set('searchQuery', localQuery)"
               class="flex-1 bg-transparent border-none focus:ring-0 text-lg placeholder-[#43474f]/40 outline-none text-[#1a1c1f]"
               placeholder="Search for PRs, Tracking IDs, Security Codes, or Employees..."
               x-ref="searchInput"
               @keydown.escape="searchOpen = false"
               x-effect="if(searchOpen) { setTimeout(() => $refs.searchInput.focus(), 100) }">
        {{-- Local spinner: only shows during search, not the global overlay --}}
        <div wire:loading wire:target="searchQuery" class="shrink-0">
            <div class="w-4 h-4 border-2 border-[#c3c6d1] border-t-[#001e40] rounded-full animate-spin"></div>
        </div>
        <button @click="searchOpen = false; localQuery = ''; $wire.set('searchQuery', '')"
                class="text-xs font-bold text-[#43474f] bg-[#f4f3f8] px-2 py-1 rounded border border-[#c3c6d1]">ESC</button>
    </div>

    <div class="p-6 max-h-[60vh] overflow-y-auto custom-scrollbar">
        @if(strlen($searchQuery) < 2)
            <div class="flex flex-col items-center justify-center py-12 text-[#43474f]/40">
                <span class="material-symbols-outlined text-6xl mb-4">manage_search</span>
                <p class="text-sm font-medium">Start typing to search across AIM...</p>
                <p class="text-[11px] mt-1 text-[#43474f]/50">Tip: You can search by System Security Code (e.g. TRK-ADB605F30505)</p>
            </div>
        @else
            @php
                $folders = $this->folders;
                $employees = $this->employees;
            @endphp

            @if($folders->isEmpty() && $employees->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-[#43474f]/40">
                    <span class="material-symbols-outlined text-6xl mb-4">search_off</span>
                    <p class="text-sm font-medium">No results found for "{{ $searchQuery }}"</p>
                    <p class="text-xs mt-1">Check spelling or try searching for another term.</p>
                </div>
            @else
                {{-- Folders / PRs --}}
                @if($folders->isNotEmpty())
                    <div class="mb-6">
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">folder_open</span>
                            <span>Purchase Requests</span>
                        </h4>
                        <div class="space-y-1">
                            @foreach($folders as $folder)
                                <button wire:click="navigateToFolder('{{ $folder->id }}')"
                                        class="w-full flex justify-between items-center p-3 hover:bg-[#f4f3f8] rounded-xl transition-all border border-transparent hover:border-[#c3c6d1] group text-left">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-[#1a1c1f] group-hover:text-[#001e40] font-mono">
                                                {{ $folder->pr_number ?: $folder->tracking_number }}
                                            </span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider
                                                {{ $folder->status === 'APPROVED' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                                {{ $folder->status_label }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-[#43474f] truncate mt-0.5">{{ $folder->overall_purpose }}</p>
                                    </div>
                                    <div class="text-right ml-4 shrink-0">
                                        <span class="text-[10px] font-mono bg-[#eeedf2] px-2 py-1 rounded text-[#43474f] font-bold uppercase">
                                            TRK-{{ strtoupper(substr(md5($folder->id), 0, 12)) }}
                                        </span>
                                        <span class="text-[10px] block text-[#43474f]/60 mt-1">{{ $folder->requesting_unit }}</span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Employees --}}
                @if($employees->isNotEmpty())
                    <div>
                        <h4 class="text-xs font-bold text-[#001e40] uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">people</span>
                            <span>Employees & Signatories</span>
                        </h4>
                        <div class="space-y-1">
                            @foreach($employees as $emp)
                                <div class="flex justify-between items-center p-3 rounded-xl border border-transparent bg-[#f9f9fe]">
                                    <div>
                                        <p class="text-sm font-bold text-[#1a1c1f]">{{ $emp->fullname }}</p>
                                        <p class="text-xs text-[#43474f]/60">{{ $emp->designation }} • {{ $emp->office_division }}</p>
                                    </div>
                                    <span class="text-xs text-[#001e40] font-medium">{{ $emp->employment_status }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        @endif
    </div>

    <div class="px-6 py-3 bg-[#f9f9fe] border-t border-[#eeedf2] flex justify-between items-center text-[10px] text-[#43474f] font-bold uppercase tracking-wider">
        <div class="flex gap-4">
            <span class="flex items-center gap-1"><b class="bg-white border border-[#c3c6d1] px-1 rounded shadow-sm">ESC</b> Close</span>
        </div>
        <p>PhilHealth Region X Command Center</p>
    </div>
</div>
