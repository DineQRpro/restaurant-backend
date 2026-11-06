@php
    /** @var \App\Models\UnitOfMeasure|null $unit */
    $unit = $unit ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @include('admin.inventory._code-field', ['code' => $unit?->code])
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Name <span class="text-red-600">*</span></label>
        <input id="name" name="name" type="text" maxlength="100" required value="{{ old('name', $unit?->name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="symbol" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Symbol <span class="text-red-600">*</span></label>
        <input id="symbol" name="symbol" type="text" maxlength="20" required value="{{ old('symbol', $unit?->symbol) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('symbol') border-red-400 @enderror">
        @error('symbol')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="dimension" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Dimension <span class="text-red-600">*</span></label>
        <select id="dimension" name="dimension" required class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('dimension') border-red-400 @enderror">
            <option value="">Select dimension</option>
            @foreach ($dimensions as $dimension)
                <option value="{{ $dimension->value }}" @selected(old('dimension', $unit?->dimension?->value) === $dimension->value)>{{ $dimension->label() }}</option>
            @endforeach
        </select>
        @error('dimension')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-[#898989]">Phase 1 records the dimension only. It does not convert between units.</p>
    </div>
    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Description</label>
        <textarea id="description" name="description" rows="3" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">{{ old('description', $unit?->description) }}</textarea>
    </div>
    @include('admin.inventory._active-field', ['active' => $unit?->is_active])
</div>
