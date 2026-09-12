<header class="sticky top-0 z-20 border-b border-[#e6ebf1] bg-white">
    <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
        <button
            type="button"
            class="inline-flex rounded-md p-2 text-[#04346a] hover:bg-[#04346a]/10 lg:hidden"
            data-sidebar-open
            aria-label="Open menu"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <div class="min-w-0 flex-1">
            <h1 class="truncate text-lg font-bold text-[#04346a] sm:text-xl">@yield('page-title', 'Counter Dashboard')</h1>
            <p class="hidden text-xs text-[#898989] sm:block">@yield('page-subtitle', 'Restaurant operations')</p>
        </div>

        <div class="text-right">
            <p class="truncate text-sm font-semibold text-[#1f2f3e]">{{ $user->name }}</p>
            <p class="text-xs capitalize text-[#898989]">{{ $user->role?->name }}</p>
        </div>
    </div>
</header>
