@php
    /** @var \App\Models\InventoryItem|null $item */
    $item = $item ?? null;
@endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @include('admin.inventory._code-field', ['code' => $item?->code])
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold">Name <span class="text-red-600">*</span></label>
        <input id="name" name="name" required maxlength="150" value="{{ old('name', $item?->name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="inventory_category_id" class="mb-1.5 block text-sm font-semibold">Category <span class="text-red-600">*</span></label>
        <select id="inventory_category_id" name="inventory_category_id" required class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('inventory_category_id') border-red-400 @enderror">
            <option value="">Select category</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('inventory_category_id', $item?->inventory_category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('inventory_category_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="unit_of_measure_id" class="mb-1.5 block text-sm font-semibold">Base unit <span class="text-red-600">*</span></label>
        <select id="unit_of_measure_id" name="unit_of_measure_id" required class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('unit_of_measure_id') border-red-400 @enderror">
            <option value="">Select unit</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected((string) old('unit_of_measure_id', $item?->unit_of_measure_id) === (string) $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>
            @endforeach
        </select>
        @error('unit_of_measure_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="reorder_level" class="mb-1.5 block text-sm font-semibold">Reorder level <span class="text-red-600">*</span></label>
        <input id="reorder_level" name="reorder_level" type="number" min="0" step="0.001" required value="{{ old('reorder_level', $item?->reorder_level) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('reorder_level') border-red-400 @enderror">
        @error('reorder_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="maximum_level" class="mb-1.5 block text-sm font-semibold">Maximum stock level <span class="text-red-600">*</span></label>
        <input id="maximum_level" name="maximum_level" type="number" min="0" step="0.001" required value="{{ old('maximum_level', $item?->maximum_level) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('maximum_level') border-red-400 @enderror">
        @error('maximum_level')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-[#898989]">Must be greater than or equal to the reorder level. This does not create stock.</p>
    </div>
    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-semibold">Description</label>
        <textarea id="description" name="description" rows="3" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">{{ old('description', $item?->description) }}</textarea>
    </div>
    @include('admin.inventory._active-field', ['active' => $item?->is_active])
</div>
