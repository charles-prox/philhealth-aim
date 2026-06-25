<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />

        <style>
            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            }
        </style>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script>
            (function () {
                const collapsed = localStorage.getItem('sidebar_collapsed') === 'true';
                if (collapsed) {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            })();
        </script>

        <style>
            /* Prevent Cumulative Layout Shift (CLS) before Alpine.js boots */
            .sidebar-collapsed aside {
                width: 5rem !important; /* w-20 */
            }
            @media (min-width: 768px) {
                .main-workspace {
                    margin-left: 16rem; /* md:ml-64 */
                }
                .sidebar-collapsed .main-workspace {
                    margin-left: 5rem !important; /* md:ml-20 */
                }
            }
            html, body {
                height: 100vh;
                min-height: 100vh !important;
                overflow: hidden;
            }
        </style>
    </head>
    <body class="font-sans antialiased text-[#1a1c1f] bg-[#f1f3f6]" 
          x-data="{ 
            sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
            searchOpen: false,
            toggleSidebar() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
                document.documentElement.classList.toggle('sidebar-collapsed', this.sidebarCollapsed);
            }
          }"
          @keydown.window.prevent.ctrl.k="searchOpen = true" 
          @keydown.window.prevent.cmd.k="searchOpen = true">
        <div class="flex h-screen overflow-hidden w-full">
            <!-- Sidebar (Fixed) -->
            <livewire:layout.navigation />

            <!-- Main Workspace -->
            <div class="main-workspace flex-1 flex flex-col min-w-0 overflow-hidden transition-all duration-300 md:ml-64" 
                 :class="sidebarCollapsed ? 'md:ml-20' : 'md:ml-64'">
                <!-- Top App Bar -->
                <header class="flex justify-between items-center px-6 h-14 w-full sticky top-0 z-40 bg-white border-b border-[#c3c6d1]">
                    <div class="flex items-center gap-4">
                        <button @click="toggleSidebar()" class="p-2 active:scale-95 transition-transform hover:bg-gray-100 rounded-lg">
                            <span class="material-symbols-outlined text-[#001e40]">menu</span>
                        </button>
                        <h1 class="text-xl font-bold text-[#001e40] tracking-tight" x-show="!sidebarCollapsed" x-transition>
                            @yield('header_title', 'Command Center')
                        </h1>
                    </div>
                    @php
                        $name = auth()->user()->name;
                        $initials = collect(explode(' ', $name))
                            ->map(fn($segment) => mb_substr($segment, 0, 1))
                            ->take(2)
                            ->join('');
                    @endphp
                    <div class="flex items-center gap-4">
                        @stack('header_actions')
                        <!-- Search Trigger Button -->
                        <button @click="searchOpen = true" class="hidden md:flex bg-[#eeedf2] px-3 py-1.5 rounded-lg items-center gap-3 border border-[#c3c6d1] hover:border-[#001e40] hover:bg-white transition-all group w-64 text-left">
                            <span class="material-symbols-outlined text-[#43474f] text-[20px] group-hover:text-[#001e40]">search</span>
                            <span class="text-sm text-[#43474f]/60 flex-1">Search assets...</span>
                            <span class="text-[10px] font-bold text-[#43474f]/40 bg-white border border-[#c3c6d1] px-1.5 py-0.5 rounded shadow-sm">CTRL K</span>
                        </button>
                        
                        <div class="relative cursor-pointer active:scale-95">
                            <span class="material-symbols-outlined text-[#001e40]">notifications</span>
                            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-[#ba1a1a] rounded-full border-2 border-white"></span>
                        </div>
                        
                        <!-- User Profile Dropdown -->
                        <div class="relative ml-2" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center gap-2 p-1 rounded-lg hover:bg-gray-100 transition-all group">
                                <div class="w-8 h-8 rounded-full bg-[#001e40] flex items-center justify-center text-white font-bold text-xs shadow-sm group-hover:scale-105 transition-transform">
                                    {{ $initials }}
                                </div>
                                <span class="material-symbols-outlined text-[#43474f] text-[18px]">expand_more</span>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="open" @click.away="open = false" 
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 class="absolute right-0 mt-2 w-48 bg-white border border-[#c3c6d1] rounded-xl shadow-xl z-50 overflow-hidden">
                                <div class="p-3 border-b border-[#eeedf2] bg-[#f9f9fe]">
                                    <p class="text-xs font-bold text-[#1a1c1f] truncate">{{ $name }}</p>
                                    <p class="text-[10px] text-[#43474f] truncate">{{ auth()->user()->username }}</p>
                                </div>
                                <a href="{{ route('profile') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#43474f] hover:bg-[#f4f3f8] hover:text-[#001e40] transition-colors" wire:navigate>
                                    <span class="material-symbols-outlined text-[20px]">person</span>
                                    Profile Settings
                                </a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-[#ba1a1a] hover:bg-red-50 transition-colors">
                                        <span class="material-symbols-outlined text-[20px]">logout</span>
                                        Sign Out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 relative overflow-y-auto focus:outline-none">
                    <div class="w-full">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <!-- Search Modal (Portal-like at Root) -->
        <div x-show="searchOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[100] flex items-start justify-center pt-24 p-4 bg-[#001e40]/40 backdrop-blur-sm"
             @click.self="searchOpen = false"
             x-cloak>
            
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl border border-[#c3c6d1] overflow-hidden"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-[-20px]"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                
                <div class="p-4 border-b border-[#eeedf2] flex items-center gap-4">
                    <span class="material-symbols-outlined text-[#001e40] text-[24px]">search</span>
                    <input type="text" 
                           class="flex-1 bg-transparent border-none focus:ring-0 text-lg placeholder-[#43474f]/40" 
                           placeholder="Search for assets, PRs, or employees..."
                           x-ref="searchInput"
                           @keydown.escape="searchOpen = false"
                           x-effect="if(searchOpen) { setTimeout(() => $refs.searchInput.focus(), 100) }">
                    <button @click="searchOpen = false" class="text-xs font-bold text-[#43474f] bg-[#f4f3f8] px-2 py-1 rounded border border-[#c3c6d1]">ESC</button>
                </div>

                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <div class="flex flex-col items-center justify-center py-12 text-[#43474f]/40">
                        <span class="material-symbols-outlined text-6xl mb-4">manage_search</span>
                        <p class="text-sm font-medium">Start typing to search across AIM...</p>
                    </div>
                </div>

                <div class="px-6 py-3 bg-[#f9f9fe] border-t border-[#eeedf2] flex justify-between items-center text-[10px] text-[#43474f] font-bold uppercase tracking-wider">
                    <div class="flex gap-4">
                        <span class="flex items-center gap-1"><b class="bg-white border border-[#c3c6d1] px-1 rounded shadow-sm">↑↓</b> Select</span>
                        <span class="flex items-center gap-1"><b class="bg-white border border-[#c3c6d1] px-1 rounded shadow-sm">ENTER</b> Open</span>
                    </div>
                    <p>PhilHealth Region X Command Center</p>
                </div>
            </div>
        </div>

        <x-toast />
        <x-confirm-modal />

        {{-- Universal Loading Indicator (Overlay for longer actions) --}}
        <div wire:loading.delay.short class="fixed inset-0 z-[200] flex items-center justify-center bg-[#001e40]/10 backdrop-blur-[2px] transition-all">
            <div class="bg-white px-8 py-5 rounded-3xl shadow-2xl border border-[#c3c6d1] flex flex-col items-center gap-4 animate-in fade-in zoom-in duration-300">
                <div class="relative">
                    <div class="w-12 h-12 border-4 border-[#eeedf2] border-t-[#001e40] rounded-full animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px] text-[#001e40] animate-pulse">database</span>
                    </div>
                </div>
                <div class="text-center">
                    <p class="text-sm font-bold text-[#001e40] uppercase tracking-widest">Processing</p>
                    <p class="text-[10px] text-[#43474f] font-bold uppercase tracking-tighter opacity-60 mt-0.5">Please wait while we sync with Master DNA...</p>
                </div>
            </div>
        </div>

        {{-- Top Progress Bar (Immediate feedback for all actions) --}}
        <div wire:loading class="fixed top-0 left-0 right-0 h-1 bg-[#001e40] z-[210] overflow-hidden">
            <div class="h-full bg-[#a7c8ff] w-full animate-[progress_2s_ease-in-out_infinite]"></div>
        </div>

        <style>
            @keyframes progress {
                0% { transform: translateX(-100%); }
                50% { transform: translateX(0); }
                100% { transform: translateX(100%); }
            }
        </style>
    </body>
</html>
