{{-- Single Employee Modal (Create & Edit) --}}
@if($showEmployeeModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showEmployeeModal', false)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl flex flex-col overflow-visible animate-in fade-in zoom-in duration-200">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe] rounded-t-2xl">
                <div>
                    <h3 class="font-bold text-[#001e40] text-[16px]">
                        {{ $editingEmployeeId ? 'Edit Employee Details' : 'Add New Employee' }}
                    </h3>
                    <p class="text-[12px] text-[#43474f]">
                        Configure HRIS ID, office assignment, and employment status.
                    </p>
                </div>
                <button wire:click="$set('showEmployeeModal', false)" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Modal Body --}}
            <form wire:submit.prevent="saveEmployee" class="p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">HRIS ID / ID Number <span class="text-red-500">*</span></label>
                        <input wire:model="empIdNumber" type="text" required placeholder="e.g. 20228900"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('empIdNumber') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input wire:model="empFullname" type="text" required placeholder="e.g. ANSHARI M. MANGONDATO"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('empFullname') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Designation <span class="text-red-500">*</span></label>
                        <input wire:model="empDesignation" type="text" required placeholder="e.g. Planning Officer III"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('empDesignation') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Salary Grade <span class="text-red-500">*</span></label>
                        <input wire:model="empSalaryGrade" type="number" min="1" max="33" required placeholder="e.g. 11"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('empSalaryGrade') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-form-select wire:model="empStatus" label="Employment Status" icon="work" required placeholder="-- Select Status --" :options="['PERMANENT' => 'PERMANENT (Regular)', 'CASUAL' => 'CASUAL', 'JO' => 'JO (Job Order)']" />
                        @error('empStatus') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-[#001e40] uppercase tracking-wider mb-1">Sub-Office / Division (Optional)</label>
                        <input wire:model="empSubOffice" type="text" placeholder="e.g. Procurement Unit"
                               class="w-full px-3 py-2 border border-[#c3c6d1] rounded-lg text-sm focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
                        @error('empSubOffice') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <x-form-select wire:model="empOfficeDivision" label="Office Assignment (Acronym)" icon="corporate_fare" required placeholder="-- Select Office --" :options="$this->offices" searchable placement="bottom" />
                    @error('empOfficeDivision') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#c3c6d1]">
                    <button type="button" wire:click="$set('showEmployeeModal', false)"
                            class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003272] transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        {{ $editingEmployeeId ? 'Save Changes' : 'Add Employee' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Bulk Import Modal --}}
@if($showBulkModal)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('showBulkModal', false)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden animate-in fade-in zoom-in duration-200">

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#c3c6d1] bg-[#f9f9fe]">
                <div>
                    <h3 class="font-bold text-[#001e40] text-[16px]">Bulk Import Employee Registry</h3>
                    <p class="text-[12px] text-[#43474f]">
                        Copy-paste columns directly from Excel or write tab/comma-separated rows.
                    </p>
                </div>
                <button wire:click="$set('showBulkModal', false)" class="p-1.5 rounded-lg text-[#43474f] hover:bg-[#eeedf2] transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="p-6 flex flex-col gap-4 overflow-y-auto max-h-[75vh] custom-scrollbar">
                
                {{-- Parsing Format Guideline --}}
                <div class="p-4 bg-indigo-50/50 border border-indigo-200 rounded-xl space-y-2 text-xs text-[#001b3c]">
                    <p class="font-bold uppercase flex items-center gap-1.5 text-indigo-900">
                        <span class="material-symbols-outlined text-[18px]">info</span> Required Columns Format (Tab or Comma Separated):
                    </p>
                    <p class="font-mono bg-white p-2 rounded border border-indigo-100 text-[10px]">
                        ID_Number &emsp; Fullname &emsp; Designation &emsp; Salary_Grade &emsp; Office_Acronym &emsp; Sub_Office (Optional) &emsp; Status (Optional)
                    </p>
                    <p class="leading-relaxed">
                        <strong>Note:</strong> Office acronym must exist in the database (e.g. <code>GSU</code>, <code>ASS</code>, <code>BAS</code>, <code>LO</code>). Status maps to <code>PERMANENT</code>, <code>CASUAL</code>, or <code>JO</code>. Matching on ID_Number will automatically update the existing employee's details.
                    </p>
                </div>

                {{-- Textarea input --}}
                <div class="flex-1 flex flex-col">
                    <label class="block text-[11px] font-bold text-[#43474f] uppercase tracking-wider mb-1.5">Paste Data Here</label>
                    <textarea wire:model="bulkText" placeholder="20228900&#9;ANSHARI M. MANGONDATO&#9;Planning Officer III&#9;11&#9;PRU&#9;&#9;Regular&#10;20214900&#9;MARIAM S. AMPASO&#9;Financial Planning Assistant B&#9;7&#9;CU&#9;&#9;Regular"
                              class="w-full h-48 px-4 py-3 border border-[#c3c6d1] rounded-xl text-xs font-mono focus:ring-2 focus:ring-[#001e40] outline-none transition-all resize-none leading-normal"></textarea>
                    @error('bulkText') <span class="text-xs text-[#ba1a1a] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Bulk Results Report --}}
                @if(!empty($bulkResults))
                    <div class="p-4 rounded-xl border {{ empty($bulkResults['skipped']) ? 'bg-green-50 border-green-200 text-green-950' : 'bg-amber-50 border-amber-200 text-amber-950' }} text-xs space-y-2">
                        <p class="font-bold flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[18px]">analytics</span>
                            Import Summary:
                        </p>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>Successfully imported/updated: <strong>{{ $bulkResults['inserted'] }}</strong> employee(s).</li>
                            @if(!empty($bulkResults['skipped']))
                                <li class="text-[#ba1a1a] font-semibold">Skipped rows: <strong>{{ count($bulkResults['skipped']) }}</strong>.</li>
                            @endif
                        </ul>
                        
                        @if(!empty($bulkResults['skipped']))
                            <div class="mt-2 max-h-32 overflow-y-auto bg-white p-3 rounded-lg border border-amber-100 font-mono text-[10px] text-[#ba1a1a] space-y-1 custom-scrollbar">
                                @foreach($bulkResults['skipped'] as $skipErr)
                                    <p>{{ $skipErr }}</p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-[#c3c6d1] mt-2">
                    <button type="button" wire:click="$set('showBulkModal', false)"
                            class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="button" wire:click="importBulk"
                            class="px-5 py-2 bg-[#001e40] text-white text-sm font-bold rounded-lg hover:bg-[#003272] transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">publish</span>
                        Parse & Import
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Delete Confirmation Modal --}}
@if($confirmingDeleteId)
    <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" wire:click.self="$set('confirmingDeleteId', null)">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in duration-200">
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-3 text-[#ba1a1a]">
                    <span class="material-symbols-outlined text-[32px]">warning</span>
                    <h4 class="font-bold text-[#001e40] text-lg">Confirm Delete</h4>
                </div>
                <p class="text-sm text-[#43474f] leading-relaxed">
                    Are you sure you want to permanently delete this employee record? This action cannot be undone and will fail if the employee is registered as an active signatory.
                </p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" wire:click="$set('confirmingDeleteId', null)"
                            class="px-4 py-2 text-sm font-bold text-[#43474f] hover:bg-[#eeedf2] rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="button" wire:click="deleteEmployee"
                            class="px-5 py-2 bg-[#ba1a1a] text-white text-sm font-bold rounded-lg hover:bg-[#93000a] transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">delete</span>
                        Delete Record
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
