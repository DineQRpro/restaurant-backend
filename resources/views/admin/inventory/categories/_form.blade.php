@php
    /** @var \App\Models\InventoryCategory|null $category */
    $category = $category ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @include('admin.inventory._code-field', ['code' => $category?->code])
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Name <span class="text-red-600">*</span></label>
        <input id="name" name="name" type="text" maxlength="150" required value="{{ old('name', $category?->name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label for="description" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Description</label>
        <textarea id="description" name="description" rows="3" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('description') border-red-400 @enderror">{{ old('description', $category?->description) }}</textarea>
        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    @include('admin.inventory._active-field', ['active' => $category?->is_active])
</div>
