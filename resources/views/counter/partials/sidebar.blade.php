<aside
    id="counter-sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#04346a] text-white transition-transform duration-200 lg:static lg:translate-x-0"
>
    <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-4">
        <a href="{{ route('counter.dashboard') }}" class="flex flex-col items-center text-center">
            <img src="{{ asset('images/admin/versity-logo-white.jpg') }}" alt="Varsity Kitchen" class="mx-auto h-16 w-auto rounded">
            <span class="mt-2 text-sm font-semibold tracking-wide">Varsity Kitchen</span>
            <span class="mt-0.5 text-[11px] uppercase tracking-wider text-white/60">Counter</span>
        </a>
        <button type="button" class="rounded p-1 text-white lg:hidden" data-sidebar-close aria-label="Close menu">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
            @foreach ($navItems as $item)
                <li>
                    @if (! empty($item['children']))
                        <div class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-white/50">
                            {{ $item['label'] }}
                        </div>
                        <ul class="mb-2 ml-3 space-y-1 border-l border-white/10 pl-3">
                            @foreach ($item['children'] as $child)
                                <li>
                                    @if (! empty($child['available']) && ! empty($child['route']))
                                        <a
                                            href="{{ route($child['route']) }}"
                                            @class([
                                                'block rounded-md px-3 py-2 text-sm font-semibold',
                                                'bg-white/15 text-white' => request()->routeIs($child['match'] ?? $child['route']),
                                                'text-white/85 hover:bg-white/10 hover:text-white' => ! request()->routeIs($child['match'] ?? $child['route']),
                                            ])
                                        >
                                            {{ $child['label'] }}
                                        </a>
                                    @else
                                        <div class="flex items-center justify-between py-1.5 text-sm text-white/40">
                                            <span>{{ $child['label'] }}</span>
                                            <span class="text-[10px] uppercase">Soon</span>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif (! empty($item['available']) && ! empty($item['route']))
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'flex items-center rounded-md px-3 py-2.5 text-sm font-semibold',
                                'bg-white/15 text-white' => request()->routeIs($item['match'] ?? $item['route']),
                                'text-white/85 hover:bg-white/10 hover:text-white' => ! request()->routeIs($item['match'] ?? $item['route']),
                            ])
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <div class="flex items-center justify-between rounded-md px-3 py-2.5 text-sm font-semibold text-white/45">
                            <span>{{ $item['label'] }}</span>
                            <span class="rounded bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">Soon</span>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="border-t border-white/10 p-3">
        <form method="POST" action="{{ route('counter.logout') }}">
            @csrf
            <button type="submit" class="w-full rounded-md px-3 py-2.5 text-left text-sm font-semibold text-white/85 hover:bg-white/10">
                Logout
            </button>
        </form>
    </div>
</aside>
