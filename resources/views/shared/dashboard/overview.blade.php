<section class="mb-5 overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="border-b border-[#eef1f5] px-5 py-4">
        <h2 class="text-lg font-bold text-[#1f2f3e]">Summary</h2>
    </div>
    <div class="px-5 py-4">
        <p class="mb-4 text-sm font-bold text-[#04346a]">{{ $dashboard['date'] }}</p>
        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($dashboard['summary'] as $stat)
                <li class="rounded-lg border border-[#eef1f5] p-4">
                    <p class="text-2xl font-bold text-[#04346a]">
                        {{ $stat['value'] ?? '—' }}
                    </p>
                    <p class="mt-1 text-sm font-semibold text-[#1f2f3e]">{{ $stat['label'] }}</p>
                    @if ($stat['placeholder'])
                        <p class="mt-2 text-xs text-[#898989]">{{ $stat['placeholder'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>

<section class="mb-5 overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-3">
        @foreach ($dashboard['highlights'] as $card)
            <article class="rounded-lg border border-[#eef1f5] p-5 text-center shadow-sm">
                <p class="text-3xl font-bold text-[#04346a]">{{ $card['value'] ?? '—' }}</p>
                <h3 class="mt-2 text-lg font-semibold">{{ $card['label'] }}</h3>
                @if ($card['placeholder'])
                    <p class="mt-2 text-xs text-[#898989]">{{ $card['placeholder'] }}</p>
                @endif
            </article>
        @endforeach
    </div>
</section>
