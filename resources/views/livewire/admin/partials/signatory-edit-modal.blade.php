{{-- Edit Slot Modal --}}
@if($showEditModal)
    <div class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-hidden max-h-[92vh] animate-in fade-in zoom-in-95 duration-200">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 bg-gradient-to-r from-[#001e40] to-[#1f477b] flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-white/10 rounded-xl flex items-center justify-center">
                        <span class="material-symbols-outlined text-white text-[20px]">edit_note</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-white text-[15px]">Configure Signatory Slot</h3>
                        <p class="text-white/60 text-[11px]">{{ $positionTitle }}</p>
                    </div>
                </div>
                <button wire:click="closeEdit" class="p-1.5 hover:bg-white/10 rounded-lg text-white/70 hover:text-white transition-all">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="overflow-y-auto flex-1 p-6 flex flex-col gap-6 custom-scrollbar">

                {{-- Position Label --}}
                <div>
                    <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1.5">Position Title</label>
                    <input wire:model="positionTitle" type="text"
                           class="w-full px-3 py-2.5 border border-[#c3c6d1] rounded-lg text-sm font-semibold focus:ring-2 focus:ring-[#001e40] outline-none transition-all text-[#001e40]"/>
                    @error('positionTitle') <p class="text-xs text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Segmented Active Holder Toggle --}}
                <div>
                    <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-2">Active Signing Authority</label>
                    <div class="flex rounded-xl border border-[#c3c6d1] overflow-hidden shadow-sm bg-[#f9f9fe]">
                        @foreach(['PRIMARY' => ['label' => 'Primary', 'color' => 'bg-[#001e40] text-white', 'icon' => 'person'],
                                  'OIC_1'   => ['label' => 'OIC 1',   'color' => 'bg-amber-500 text-white',  'icon' => 'swap_horiz'],
                                  'OIC_2'   => ['label' => 'OIC 2',   'color' => 'bg-orange-500 text-white', 'icon' => 'swap_calls']] as $value => $config)
                            <button type="button" wire:click="$set('activeHolder', '{{ $value }}')"
                                    class="flex-1 py-2.5 text-xs font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 transition-all border-r border-[#c3c6d1] last:border-0
                                           {{ $activeHolder === $value ? $config['color'] . ' shadow-inner' : 'text-[#43474f] hover:bg-[#eeedf2]' }}">
                                <span class="material-symbols-outlined text-[16px]">{{ $config['icon'] }}</span>
                                {{ $config['label'] }}
                            </button>
                        @endforeach
                    </div>
                    @error('activeHolder') <p class="text-xs text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    <p class="text-[10px] text-[#43474f]/60 mt-1.5 italic">This determines whose name appears on PR documents when generated. The other slots serve as designated backup contacts only.</p>
                </div>

                {{-- Employee Slots --}}
                @foreach([
                    ['label' => 'Primary Holder', 'required' => true,  'icon' => 'person',       'badgeClass' => 'bg-[#001e40] text-white',    'searchProp' => 'primarySearch',  'matchesProp' => 'primaryMatches',  'selectedId' => $primaryEmployeeId,  'selectAction' => 'selectPrimary',  'clearAction' => 'clearPrimary', 'errorKey' => 'primaryEmployeeId'],
                    ['label' => 'OIC 1 (First Designate)', 'required' => false, 'icon' => 'swap_horiz', 'badgeClass' => 'bg-amber-500 text-white', 'searchProp' => 'oic1Search',    'matchesProp' => 'oic1Matches',    'selectedId' => $oicPrimaryId,      'selectAction' => 'selectOic1',    'clearAction' => 'clearOic1', 'errorKey' => 'oicPrimaryId'],
                    ['label' => 'OIC 2 (Fallback Designate)', 'required' => false, 'icon' => 'swap_calls', 'badgeClass' => 'bg-orange-500 text-white', 'searchProp' => 'oic2Search', 'matchesProp' => 'oic2Matches',    'selectedId' => $oicSecondaryId,    'selectAction' => 'selectOic2',    'clearAction' => 'clearOic2', 'errorKey' => 'oicSecondaryId'],
                ] as $slot)
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full {{ $slot['badgeClass'] }} flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined text-[13px]">{{ $slot['icon'] }}</span>
                            </span>
                            <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider">
                                {{ $slot['label'] }}{!! $slot['required'] ? ' <span class="text-[#ba1a1a]">*</span>' : ' <span class="text-[#43474f]/40 font-normal">(optional)</span>' !!}
                            </label>
                        </div>

                        @if($slot['selectedId'])
                            @php $emp = App\Models\Employee::find($slot['selectedId']); @endphp
                            <div class="flex items-center gap-3 p-3 bg-[#f9f9fe] border border-[#c3c6d1] rounded-xl">
                                <div class="w-9 h-9 rounded-xl {{ $slot['badgeClass'] }} flex items-center justify-center font-bold text-sm flex-shrink-0">
                                    {{ strtoupper(substr($emp?->fullname ?? '?', 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-[#001e40] text-sm truncate">{{ $emp?->fullname ?? 'Unknown' }}</p>
                                    <p class="text-[10px] text-[#43474f]/70 truncate">{{ $emp?->designation ?? '—' }} · {{ $emp?->office_division ?? '—' }}</p>
                                </div>
                                @if($slot['clearAction'])
                                    <button type="button" wire:click="{{ $slot['clearAction'] }}"
                                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg transition-all flex-shrink-0 text-[11px] font-bold border
                                                   {{ $slot['required'] ? 'text-[#001e40] border-[#c3c6d1] hover:bg-[#eeedf2] hover:border-[#001e40]' : 'text-[#ba1a1a] border-red-100 hover:bg-red-50' }}"
                                            title="{{ $slot['required'] ? 'Change to a different person' : 'Remove this assignment' }}">
                                        <span class="material-symbols-outlined text-[15px]">{{ $slot['required'] ? 'edit' : 'link_off' }}</span>
                                        {{ $slot['required'] ? 'Change' : 'Remove' }}
                                    </button>
                                @endif
                            </div>
                        @else
                            <div x-data="{ focused: false }" class="relative">
                                <div class="relative">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#43474f] text-[18px]">search</span>
                                    <input wire:model.live.debounce.250ms="{{ $slot['searchProp'] }}"
                                           type="text"
                                           placeholder="Type 2+ letters to find employee..."
                                           @focus="focused = true" @blur="setTimeout(() => focused = false, 150)"
                                           class="w-full pl-9 pr-4 py-2.5 bg-white border border-[#c3c6d1] rounded-xl text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all placeholder-[#43474f]/40"/>
                                </div>
                                @if(strlen($this->{$slot['searchProp']}) >= 2)
                                    <div class="absolute z-10 mt-1 w-full bg-white border border-[#c3c6d1] rounded-xl shadow-lg overflow-hidden max-h-52 overflow-y-auto custom-scrollbar">
                                        @forelse($this->{$slot['matchesProp']} as $emp)
                                            <button type="button" wire:click="{{ $slot['selectAction'] }}({{ $emp->id }})"
                                                    class="w-full text-left px-3 py-2.5 flex items-center gap-3 hover:bg-[#f4f3f8] transition-colors border-b border-[#eeedf2] last:border-0">
                                                <div class="w-8 h-8 rounded-lg bg-[#001e40] text-white flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                                                    {{ strtoupper(substr($emp->fullname, 0, 1)) }}
                                                </div>
                                                <div class="flex flex-col min-w-0">
                                                    <span class="font-bold text-[#001e40] text-[12px] truncate">{{ $emp->fullname }}</span>
                                                    <span class="text-[10px] text-[#43474f] truncate">{{ $emp->designation }} · {{ $emp->office_division }}</span>
                                                </div>
                                            </button>
                                        @empty
                                            <div class="px-4 py-3 text-xs text-[#43474f]/60 italic text-center">No employees match "{{ $this->{$slot['searchProp']} }}"</div>
                                        @endforelse
                                    </div>
                                @endif
                            </div>
                        @endif
                        @error($slot['errorKey']) <p class="text-[11px] text-[#ba1a1a] mt-1">{{ $message }}</p> @enderror
                    </div>
                @endforeach

            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-[#eeedf2] bg-[#f9f9fe] flex-shrink-0">
                <p class="text-[10px] text-[#43474f]/60 italic flex items-center gap-1">
                    <span class="material-symbols-outlined text-[13px]">info</span>
                    Changes take effect immediately on all new PR drafts.
                </p>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="closeEdit" class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-xl transition-all">Cancel</button>
                    <button type="button" wire:click="saveSlot" wire:loading.attr="disabled"
                            class="px-6 py-2 bg-[#001e40] hover:bg-[#003272] disabled:opacity-50 text-white text-sm font-bold rounded-xl transition-all flex items-center gap-2 shadow-sm">
                        <span wire:loading wire:target="saveSlot" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        <span wire:loading.remove wire:target="saveSlot" class="material-symbols-outlined text-[18px]">save</span>
                        Save Configuration
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
