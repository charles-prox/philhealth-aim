            {{-- Welcome Banner matching system style --}}
            <div class="bg-[#001e40] rounded-xl p-6 flex items-center justify-between overflow-hidden relative shadow-lg">
                <div class="relative z-10">
                    <p class="text-[#a7c8ff] font-bold text-[12px] uppercase tracking-widest mb-1">PhilHealth AIM · Region X</p>
                    <h2 class="text-2xl font-bold text-white">
                        {{ $isReadOnly ? 'Office Division Registry' : 'Procurement Workspace' }}
                    </h2>
                    <p class="text-white/60 text-sm mt-1">
                        Track requests, manage active purchasing pipelines, and generate validated digital PR sheets.
                    </p>
                </div>
                <div class="hidden md:block relative z-10">
                    <span class="material-symbols-outlined text-[64px] text-white/10">receipt_long</span>
                </div>
                {{-- Decorative circles matching dashboard --}}
                <div class="absolute -right-8 -top-8 w-48 h-48 rounded-full bg-white/5 pointer-events-none"></div>
                <div class="absolute -right-2 -bottom-10 w-36 h-36 rounded-full bg-white/5 pointer-events-none"></div>
            </div>

            {{-- APP Warning Banner --}}
            @if(!$appGateCleared)
                <div class="flex items-start gap-4 bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 rounded-xl shadow-sm">
                    <span class="material-symbols-outlined text-amber-600 text-[28px] mt-0.5" style="font-variation-settings: 'FILL' 1;">warning</span>
                    <div class="flex-1">
                        <h4 class="text-sm font-bold text-amber-950">Procurement Pipeline Suspended</h4>
                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            No active Annual Procurement Plan (APP) has been approved for the current fiscal year ({{ $currentYear }}). 
                            Purchase Request compilation is locked until the APP is uploaded and activated by the Admin Head.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Success & Error Banners --}}
            @if($successMessage)
                <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-5 py-3 rounded-xl shadow-sm" x-data="{ show: true }" x-show="show">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <p class="text-sm font-bold flex-1">{{ $successMessage }}</p>
                    <button @click="show = false" wire:click="$set('successMessage', null)" class="p-1 hover:bg-green-100 rounded-lg">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            @if(session('error') || $errorMessage)
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 px-5 py-3 rounded-xl shadow-sm" x-data="{ show: true }" x-show="show">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <p class="text-sm font-bold flex-1">{{ session('error') ?? $errorMessage }}</p>
                    <button @click="show = false" @if($errorMessage) wire:click="$set('errorMessage', null)" @endif class="p-1 hover:bg-red-100 rounded-lg">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                </div>
            @endif

            {{-- KPI Summary Row matching system bento design --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter">
                <!-- Active Requests -->
                <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Active Requests</span>
                            <div class="w-9 h-9 bg-[#001e40]/8 rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#001e40] text-[20px]" style="font-variation-settings: 'FILL' 1;">description</span>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-[#001e40]">{{ $totalActive }}</p>
                    </div>
                    <p class="text-[10px] text-[#43474f]/70 mt-3 uppercase tracking-wider font-bold">Currently in Procurement Queue</p>
                </div>

                <!-- Drafts Pending -->
                <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Pending Drafts</span>
                            <div class="w-9 h-9 bg-[#ffdbca]/40 rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#723610] text-[20px]" style="font-variation-settings: 'FILL' 1;">edit_note</span>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-[#001e40]">{{ $totalPending }}</p>
                    </div>
                    <p class="text-[10px] text-[#43474f]/70 mt-3 uppercase tracking-wider font-bold">Awaiting Submission</p>
                </div>

                <!-- Fiscal Status -->
                <div class="bg-white border border-[#c3c6d1] p-gutter rounded-xl shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <span class="text-[12px] font-bold text-[#43474f] uppercase tracking-wider">Fiscal Gateway</span>
                            <div class="w-9 h-9 bg-[#d8e1ea]/60 rounded-xl flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#3a5f94] text-[20px]" style="font-variation-settings: 'FILL' 1;">verified_user</span>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-[#001e40]">FY {{ $currentYear }}</p>
                    </div>
                    <div class="mt-3 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider">
                        @if($appGateCleared)
                            <span class="text-green-700">● APP Active</span>
                        @else
                            <span class="text-red-700">● Suspended</span>
                        @endif
                    </div>
                </div>
            </div>

