<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'PhilHealth AIM') }} - Secure Login</title>

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            .material-symbols-outlined {
                font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
                display: inline-block;
                line-height: 1;
                text-transform: none;
                letter-spacing: normal;
                word-wrap: normal;
                white-space: nowrap;
                direction: ltr;
            }
            body {
                background-color: #f9f9fe;
                min-height: max(884px, 100dvh);
            }
        </style>
    </head>
    <body class="bg-background text-[#1a1c1f] font-sans min-h-screen flex flex-col">
        <!-- Main Content Area -->
        <main class="flex-grow flex items-center justify-center p-6 lg:p-12">
            {{ $slot }}
        </main>

        <!-- Simplified Footer -->
        <footer class="py-8 text-center bg-transparent">
            <div class="flex items-center justify-center gap-4 opacity-50">
                <x-application-logo class="h-5 fill-current text-[#001e40]" />
                <div class="h-4 w-px bg-[#c3c6d1]"></div>
                <span class="text-[10px] font-bold uppercase tracking-widest text-[#43474f]">© {{ date('Y') }} PhilHealth AIM. Region X.</span>
            </div>
        </footer>
    </body>
</html>
