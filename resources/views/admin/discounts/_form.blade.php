@php
    /** @var \App\Models\Discount|null $discount */
    $discount = $discount ?? null;
    $types = $types ?? [];
    $appliesToOptions = $appliesToOptions ?? [];
    $startDate = old('start_date', $discount?->start_date?->format('Y-m-d\TH:i'));
    $endDate = old('end_date', $discount?->end_date?->format('Y-m-d\TH:i'));
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Discount Name <span class="text-red-600">*</span>
        </label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $discount?->name) }}"
            maxlength="150"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Discount Type <span class="text-red-600">*</span>
        </label>
        <select
            id="type"
            name="type"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('type') border-red-400 @enderror"
        >
            <option value="">Select Type</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected((string) old('type', $discount?->type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        @error('type')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="value" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Discount Percentage (%) <span class="text-red-600">*</span>
        </label>
        <input
            id="value"
            type="number"
            name="value"
            min="0"
            max="100"
            step="0.01"
            value="{{ old('value', $discount?->value ?? 0) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('value') border-red-400 @enderror"
        >
        @error('value')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-[#898989]">Fixed discounts store this percentage. Custom discounts typically use 0 so staff enter a percentage later.</p>
    </div>

    <div>
        <label for="applies_to" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Applies To <span class="text-red-600">*</span>
        </label>
        <select
            id="applies_to"
            name="applies_to"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('applies_to') border-red-400 @enderror"
        >
            <option value="">Select</option>
            @foreach ($appliesToOptions as $appliesTo)
                <option value="{{ $appliesTo->value }}" @selected((string) old('applies_to', $discount?->applies_to?->value) === $appliesTo->value)>
                    {{ $appliesTo->label() }}
                </option>
            @endforeach
        </select>
        @error('applies_to')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="min_level" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Minimum Level</label>
        <input
            id="min_level"
            type="number"
            name="min_level"
            min="0"
            max="100"
            value="{{ old('min_level', $discount?->min_level ?? 0) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('min_level') border-red-400 @enderror"
        >
        @error('min_level')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-[#898989]">For Custom, lowest allowed staff-entered percentage. Use 0 when unused.</p>
    </div>

    <div>
        <label for="max_level" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Maximum Level</label>
        <input
            id="max_level"
            type="number"
            name="max_level"
            min="0"
            max="100"
            value="{{ old('max_level', $discount?->max_level ?? 0) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('max_level') border-red-400 @enderror"
        >
        @error('max_level')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-[#898989]">For Custom, highest allowed staff-entered percentage. Use 0 when unused.</p>
    </div>

    <div>
        <label for="start_date" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Start Date / Time</label>
        <input
            id="start_date"
            type="datetime-local"
            name="start_date"
            value="{{ $startDate }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('start_date') border-red-400 @enderror"
        >
        @error('start_date')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="end_date" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">End Date / Time</label>
        <input
            id="end_date"
            type="datetime-local"
            name="end_date"
            value="{{ $endDate }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('end_date') border-red-400 @enderror"
        >
        @error('end_date')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
            <input type="hidden" name="is_active" value="0">
            <input
                type="checkbox"
                name="is_active"
                value="1"
                class="rounded border-[#dfdfdf] text-[#04346a]"
                @checked((string) old('is_active', $discount?->is_active ?? true) === '1' || old('is_active', $discount?->is_active ?? true) === true)
            >
            Active
        </label>
    </div>
</div>
