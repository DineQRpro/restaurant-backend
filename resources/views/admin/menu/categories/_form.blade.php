@php
    /** @var \App\Models\MenuCategory|null $category */
    $category = $category ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="menu_type_id" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Menu Type <span class="text-red-600">*</span>
        </label>
        <select
            id="menu_type_id"
            name="menu_type_id"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('menu_type_id') border-red-400 @enderror"
        >
            <option value="">Choose...</option>
            @foreach ($menuTypes as $menuType)
                <option value="{{ $menuType->id }}" @selected((string) old('menu_type_id', $category?->menu_type_id) === (string) $menuType->id)>
                    {{ $menuType->name }}
                </option>
            @endforeach
        </select>
        @error('menu_type_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Cuisine Title <span class="text-red-600">*</span>
        </label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $category?->name) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Cuisine Description</label>
        <input
            id="description"
            type="text"
            name="description"
            value="{{ old('description', $category?->description) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('description') border-red-400 @enderror"
        >
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="image" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Cuisine Image</label>
        <input
            id="image"
            type="file"
            name="image"
            accept="image/jpeg,image/png,image/webp"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2 text-sm outline-none focus:border-[#04346a] @error('image') border-red-400 @enderror"
        >
        @error('image')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @if ($category?->image)
            <div class="mt-3">
                <img
                    src="{{ str_starts_with($category->image, 'http') ? $category->image : asset('storage/'.$category->image) }}"
                    alt="{{ $category->name }}"
                    class="h-20 w-20 rounded object-cover"
                >
            </div>
        @endif
    </div>

    <div>
        <label for="sort_order" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Sort order</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            min="0"
            value="{{ old('sort_order', $category?->sort_order ?? 0) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('sort_order') border-red-400 @enderror"
        >
        @error('sort_order')
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
                @checked((string) old('is_active', $category?->is_active ?? true) === '1' || old('is_active', $category?->is_active ?? true) === true)
            >
            Active
        </label>
    </div>
</div>
