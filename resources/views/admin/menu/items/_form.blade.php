@php
    /** @var \App\Models\MenuItem|null $item */
    $item = $item ?? null;
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Menu Name <span class="text-red-600">*</span>
        </label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $item?->name) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Menu Description</label>
        <input
            id="description"
            type="text"
            name="description"
            value="{{ old('description', $item?->description) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('description') border-red-400 @enderror"
        >
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="category_id" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Cuisine <span class="text-red-600">*</span>
        </label>
        <select
            id="category_id"
            name="category_id"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('category_id') border-red-400 @enderror"
        >
            <option value="">Choose...</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $item?->category_id) === (string) $category->id)>
                    {{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="dietary_type" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Dietary Type <span class="text-red-600">*</span>
        </label>
        <select
            id="dietary_type"
            name="dietary_type"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('dietary_type') border-red-400 @enderror"
        >
            <option value="">Choose...</option>
            @foreach (\App\Enums\DietaryType::cases() as $type)
                <option value="{{ $type->value }}" @selected(old('dietary_type', $item?->dietary_type?->value) === $type->value)>
                    {{ $type->label() }}
                </option>
            @endforeach
        </select>
        @error('dietary_type')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Product Price <span class="text-red-600">*</span>
        </label>
        <input
            id="price"
            type="text"
            name="price"
            inputmode="decimal"
            value="{{ old('price', $item?->price) }}"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('price') border-red-400 @enderror"
        >
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="spicy_level" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Spicy Level</label>
        <select
            id="spicy_level"
            name="spicy_level"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('spicy_level') border-red-400 @enderror"
        >
            <option value="0" @selected((string) old('spicy_level', $item?->spicy_level ?? 0) === '0')>Not Spicy</option>
            <option value="1" @selected((string) old('spicy_level', $item?->spicy_level) === '1')>Spicy</option>
            <option value="2" @selected((string) old('spicy_level', $item?->spicy_level) === '2')>Very Spicy</option>
        </select>
        @error('spicy_level')
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
            value="{{ old('sort_order', $item?->sort_order ?? 0) }}"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('sort_order') border-red-400 @enderror"
        >
        @error('sort_order')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col justify-end gap-3 md:col-span-2">
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
            <input type="hidden" name="is_chef_special" value="0">
            <input
                type="checkbox"
                name="is_chef_special"
                value="1"
                class="rounded border-[#dfdfdf] text-[#04346a]"
                @checked((string) old('is_chef_special', $item?->is_chef_special ?? false) === '1' || old('is_chef_special', $item?->is_chef_special ?? false) === true)
            >
            Chef's Special
        </label>
        <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
            <input type="hidden" name="is_available" value="0">
            <input
                type="checkbox"
                name="is_available"
                value="1"
                class="rounded border-[#dfdfdf] text-[#04346a]"
                @checked((string) old('is_available', $item?->is_available ?? true) === '1' || old('is_available', $item?->is_available ?? true) === true)
            >
            Available
        </label>
    </div>
</div>
