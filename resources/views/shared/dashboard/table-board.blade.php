<section class="mb-5 overflow-hidden rounded-xl bg-white shadow-sm">
    <div class="border-b border-[#eef1f5] px-5 py-4">
        <h2 class="text-lg font-bold text-[#1f2f3e]">Tables</h2>
    </div>
    <div class="p-5">
        <ul class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <li class="rounded-lg border border-[#eef1f5] p-3">
                <p class="text-xl font-bold text-[#04346a]">{{ $dashboard['table_counts']['total'] }}</p>
                <p class="mt-1 text-xs font-semibold text-[#1f2f3e]">Total Active Tables</p>
            </li>
            <li class="rounded-lg border border-[#eef1f5] p-3">
                <p class="text-xl font-bold text-[#04346a]">{{ $dashboard['table_counts']['available'] }}</p>
                <p class="mt-1 text-xs font-semibold text-[#1f2f3e]">Available Tables</p>
            </li>
            <li class="rounded-lg border border-[#eef1f5] p-3">
                <p class="text-xl font-bold text-[#04346a]">{{ $dashboard['table_counts']['occupied'] }}</p>
                <p class="mt-1 text-xs font-semibold text-[#1f2f3e]">Occupied Tables</p>
            </li>
            <li class="rounded-lg border border-[#eef1f5] p-3">
                <p class="text-xl font-bold text-[#04346a]">{{ $dashboard['table_counts']['cleaning'] }}</p>
                <p class="mt-1 text-xs font-semibold text-[#1f2f3e]">Cleaning Tables</p>
            </li>
        </ul>

        <div class="mb-4 flex flex-wrap gap-2 text-xs font-semibold">
            @foreach ($dashboard['table_statuses'] as $status)
                <span class="admin-table-status-legend {{ $status['css_class'] }}">{{ $status['label'] }}</span>
            @endforeach
        </div>

        @if ($dashboard['tables']->isEmpty())
            <div class="rounded-lg border border-dashed border-[#d5dde7] bg-[#f8fafc] p-6 text-center">
                <p class="font-semibold text-[#1f2f3e]">No active tables.</p>
            </div>
        @else
            <div class="admin-table-board">
                @foreach ($dashboard['tables'] as $table)
                    <article class="admin-table-board-card {{ $table->status->cssClass() }}">
                        <p class="text-lg font-bold leading-tight">Table {{ $table->number }}</p>
                        <p class="mt-1 text-sm font-semibold">{{ $table->status->label() }}</p>
                        <p class="mt-1 text-xs opacity-90">
                            {{ $table->capacity }} {{ $table->capacity === 1 ? 'Chair' : 'Chairs' }}
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
