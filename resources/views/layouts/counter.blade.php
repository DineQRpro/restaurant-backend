<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Counter') — Varsity Kitchen</title>
    <link rel="icon" href="{{ asset('images/admin/versity-logo-white.jpg') }}" type="image/jpeg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="admin-app min-h-screen bg-[#f4f6f9] text-[#1f2f3e] antialiased @yield('body-class')">
    @php
        $navItems = app(\App\Support\CounterNavigation::class)->items(auth()->user());
        $user = auth()->user();
    @endphp

    <div class="flex min-h-screen">
        @include('counter.partials.sidebar', ['navItems' => $navItems, 'user' => $user])

        <div class="flex min-w-0 flex-1 flex-col">
            @include('counter.partials.header', ['user' => $user])

            <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
                @include('counter.partials.alerts')
                @yield('content')
            </main>
        </div>
    </div>

    <div
        id="counter-sidebar-backdrop"
        class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"
        data-sidebar-close
    ></div>

    <script>
        (() => {
            const sidebar = document.getElementById('counter-sidebar');
            const backdrop = document.getElementById('counter-sidebar-backdrop');
            const openers = document.querySelectorAll('[data-sidebar-open]');
            const closers = document.querySelectorAll('[data-sidebar-close]');

            const open = () => {
                sidebar?.classList.remove('-translate-x-full');
                backdrop?.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            };
            const close = () => {
                sidebar?.classList.add('-translate-x-full');
                backdrop?.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };

            openers.forEach((el) => el.addEventListener('click', open));
            closers.forEach((el) => el.addEventListener('click', close));
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) close();
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
