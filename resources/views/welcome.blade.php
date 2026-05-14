<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>PhilHealth AIM - Region X</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans text-[#1a1c1f] bg-[#f9f9fe]">
        <div class="relative min-h-screen flex flex-col items-center justify-center overflow-hidden">
            <!-- Institutional Background Accents -->
            <div class="absolute top-0 right-0 w-1/3 h-full bg-[#f4f3f8] -skew-x-12 transform translate-x-1/2 z-0"></div>
            
            <div class="relative z-10 w-full max-w-4xl px-6 py-12 flex flex-col md:flex-row items-center gap-12">
                <!-- Branding Section -->
                <div class="flex-1 text-center md:text-left">
                    <div class="inline-flex items-center space-x-4 mb-8">
                        <x-application-logo class="w-16 h-16 fill-current text-[#001e40]" />
                        <div class="h-12 w-px bg-[#e2e2e7]"></div>
                        <div class="text-left">
                            <h2 class="text-xs font-black uppercase tracking-[0.3em] text-[#737780] leading-none mb-1">Government of the Philippines</h2>
                            <p class="text-sm font-bold text-[#001e40] uppercase tracking-tighter">PhilHealth Insurance Corporation</p>
                        </div>
                    </div>
                    
                    <h1 class="text-5xl font-black text-[#001e40] tracking-tighter leading-none mb-6">
                        Admin Inventory <br/>
                        <span class="text-[#799dd6]">Management</span>
                    </h1>
                    
                    <p class="text-lg text-[#43474f] leading-relaxed mb-8 max-w-md">
                        Official centralized resource tracking and procurement system for Region X Northern Mindanao. Ensuring transparency and zero-encoding data integrity.
                    </p>

                    <div class="flex flex-wrap gap-4 justify-center md:justify-start">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="btn-primary py-3 px-8 text-base shadow-lg shadow-[#001e40]/20">
                                    {{ __('Enter Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary py-3 px-8 text-base shadow-lg shadow-[#001e40]/20">
                                    {{ __('Secure Sign In') }}
                                </a>
                            @endauth
                        @endif
                        <button class="btn-secondary py-3 px-8 text-base">
                            {{ __('System Manual') }}
                        </button>
                    </div>
                </div>

                <!-- Visual Asset / Card -->
                <div class="flex-1 w-full max-w-sm">
                    <div class="card p-8 bg-white/80 backdrop-blur-sm border-[#001e40]/10 shadow-2xl">
                        <div class="space-y-6">
                            <div class="pb-6 border-b border-gray-100 text-center">
                                <p class="text-[10px] font-black uppercase tracking-widest text-[#799dd6] mb-2">{{ __('Current Status') }}</p>
                                <div class="inline-flex items-center text-emerald-600 font-bold">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse mr-2"></span>
                                    {{ __('PRO-X Region Node Online') }}
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-[#43474f] uppercase tracking-wider">{{ __('Version') }}</span>
                                    <span class="text-xs font-bold text-[#001e40]">v2.1.0-stable</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-[#43474f] uppercase tracking-wider">{{ __('Last Sync') }}</span>
                                    <span class="text-xs font-bold text-[#001e40]">{{ now()->format('d M Y') }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-[#43474f] uppercase tracking-wider">{{ __('Encryption') }}</span>
                                    <span class="text-xs font-bold text-emerald-600 uppercase">AES-256</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="mt-auto w-full py-8 px-6 border-t border-[#e2e2e7] bg-white z-10">
                <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-[10px] font-bold text-[#737780] uppercase tracking-widest">
                        &copy; {{ date('Y') }} PhilHealth PRO-X. All Rights Reserved.
                    </p>
                    <div class="flex space-x-6">
                        <span class="text-[10px] font-bold text-[#737780] uppercase tracking-widest">{{ __('Privacy Policy') }}</span>
                        <span class="text-[10px] font-bold text-[#737780] uppercase tracking-widest">{{ __('Terms of Use') }}</span>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
