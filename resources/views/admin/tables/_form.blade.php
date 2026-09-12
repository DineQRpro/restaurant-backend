@php
    /** @var \App\Models\Table|null $table */
    $table = $table ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="number" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Table Number <span class="text-red-600">*</span>
        </label>
        <input
            id="number"
            type="text"
            name="number"
            value="{{ old('number', $table?->number) }}"
            placeholder="e.g. 12 or T1"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('number') border-red-400 @enderror"
        >
        @error('number')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Table Description</label>
        <input
            id="description"
            type="text"
            name="description"
            value="{{ old('description', $table?->description) }}"
            placeholder="Optional description"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('description') border-red-400 @enderror"
        >
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="capacity" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Table Chair Count <span class="text-red-600">*</span>
        </label>
        <input
            id="capacity"
            type="number"
            name="capacity"
            min="1"
            max="99"
            value="{{ old('capacity', $table?->capacity) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('capacity') border-red-400 @enderror"
        >
        @error('capacity')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Sort order</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            min="0"
            value="{{ old('sort_order', $table?->sort_order ?? 0) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('sort_order') border-red-400 @enderror"
        >
        @error('sort_order')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    @if ($table)
        <div class="md:col-span-2">
            <p class="text-sm text-[#898989]">QR identifier: <span class="font-mono text-[#1f2f3e]">{{ $table->code }}</span></p>
        </div>
    @endif

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
            <input type="hidden" name="is_active" value="0">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                class="rounded border-[#dfdfdf] text-[#04346a]"
                @checked((string) old('is_active', $table?->is_active ?? true) === '1' || old('is_active', $table?->is_active ?? true) === true)
            >
            Active
        </label>
    </div>
</div>
