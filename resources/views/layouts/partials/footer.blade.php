<!-- resources/views/layouts/partials/footer.blade.php -->
<footer x-data="{ showChangelog: false }" 
        class="w-full bg-surface py-3.5 px-6 border-t border-outline-variant flex flex-col md:flex-row justify-between items-center text-[11px] text-on-surface-variant font-medium select-none">
    
    <!-- Left Section: Copyright & Branding -->
    <div class="flex items-center gap-2">
        <span>&copy; {{ date('Y') }} PhilHealth Regional Office X.</span>
        <span class="text-outline">|</span>
        <span class="font-bold text-primary">General Services Unit (GSU)</span>
    </div>

    <!-- Right Section: Interactive Version Tag -->
    <div @click="showChangelog = true" 
         class="flex items-center gap-1.5 mt-2 md:mt-0 font-mono bg-surface-container-high hover:bg-surface-container-highest transition-colors px-2 py-0.5 rounded border border-outline-variant cursor-pointer">
        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
        <span>Version:</span>
        <span class="font-bold text-on-surface underline decoration-dotted">{{ config('app.version') }}</span>
    </div>

    <!-- Lightweight Changelog Modal (Alpine.js Controlled) -->
    <template x-teleport="body">
        <div x-show="showChangelog" 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             x-cloak>
             
            <!-- Modal Body Container -->
            <div @click.outside="showChangelog = false" 
                 class="w-full max-w-md bg-surface border border-outline-variant rounded-xl shadow-2xl p-5 relative">
                
                <!-- Close Button -->
                <button @click="showChangelog = false" class="absolute top-4 right-4 text-on-surface-variant hover:text-on-surface">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>

                <!-- Header -->
                <h3 class="text-sm font-bold text-on-surface flex items-center gap-2">
                    📦 What's New in PhilHealth-AIM
                </h3>
                <p class="text-[10px] text-outline-variant font-semibold mt-0.5">Currently Running Version: {{ config('app.version') }}</p>

                <!-- Changelog Content Body -->
                <div class="mt-4 space-y-3 max-h-64 overflow-y-auto pr-1">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 bg-primary-container text-on-primary-container rounded">System Feature</span>
                        <p class="text-xs font-semibold mt-1 text-on-surface">Modular System Redesign</p>
                        <p class="text-[11px] text-on-surface-variant leading-relaxed">Successfully refactored the monolithic Procurement Wizard into separate, lightweight Blade partial structures and decoupled business operations into service layers.</p>
                    </div>
                    
                    <div class="pt-2.5 border-t border-outline-variant">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 bg-secondary-container text-on-secondary-container rounded">GSU Optimization</span>
                        <p class="text-xs font-semibold mt-1 text-on-surface">Dynamic Business Line Sorter & Event Tracking</p>
                        <p class="text-[11px] text-on-surface-variant leading-relaxed">Added conditional step-1 target trackers to flag event dates on time-sensitive folders and tag supplier lines.</p>
                    </div>
                </div>

                <!-- Action Button Footer -->
                <div class="mt-5 pt-3 border-t border-outline-variant flex justify-end">
                    <button @click="showChangelog = false" 
                            class="px-3.5 py-1.5 bg-primary text-on-primary hover:bg-[#1f3f66] font-bold text-xs rounded-lg shadow transition-colors">
                        Understood
                    </button>
                </div>
            </div>
        </div>
    </template>
</footer>
