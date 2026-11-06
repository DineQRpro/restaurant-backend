@php
    /** @var \App\Models\Vendor|null $vendor */
    $vendor = $vendor ?? null;
@endphp
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    @include('admin.inventory._code-field', ['code' => $vendor?->code])
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold">Vendor name <span class="text-red-600">*</span></label>
        <input id="name" name="name" required maxlength="150" value="{{ old('name', $vendor?->name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror">
        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-[#898989]">This is the primary vendor name shown on goods receipts.</p>
    </div>
    <div>
        <label for="shop_name" class="mb-1.5 block text-sm font-semibold">Shop name <span class="text-red-600">*</span></label>
        <input id="shop_name" name="shop_name" required maxlength="150" value="{{ old('shop_name', $vendor?->shop_name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('shop_name') border-red-400 @enderror">
        @error('shop_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="poc_name" class="mb-1.5 block text-sm font-semibold">POC name <span class="text-red-600">*</span></label>
        <input id="poc_name" name="poc_name" required maxlength="150" value="{{ old('poc_name', $vendor?->poc_name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('poc_name') border-red-400 @enderror">
        @error('poc_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="phone" class="mb-1.5 block text-sm font-semibold">Phone</label>
        <input id="phone" name="phone" maxlength="30" value="{{ old('phone', $vendor?->phone) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
    </div>
    <div>
        <label for="gst_number" class="mb-1.5 block text-sm font-semibold">GST number</label>
        <input id="gst_number" name="gst_number" maxlength="30" value="{{ old('gst_number', $vendor?->gst_number) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
    </div>
    <div class="md:col-span-2">
        <label for="address" class="mb-1.5 block text-sm font-semibold">Address</label>
        <textarea id="address" name="address" rows="3" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">{{ old('address', $vendor?->address) }}</textarea>
    </div>
    <div>
        <label for="bank_name" class="mb-1.5 block text-sm font-semibold">Bank name</label>
        <input id="bank_name" name="bank_name" maxlength="150" value="{{ old('bank_name', $vendor?->bank_name) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('bank_name') border-red-400 @enderror">
        @error('bank_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="ifsc_code" class="mb-1.5 block text-sm font-semibold">IFSC code</label>
        <input id="ifsc_code" name="ifsc_code" maxlength="11" value="{{ old('ifsc_code', $vendor?->ifsc_code) }}" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm uppercase outline-none focus:border-[#04346a] @error('ifsc_code') border-red-400 @enderror">
        @error('ifsc_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="md:col-span-2">
        <label for="bank_account_number" class="mb-1.5 block text-sm font-semibold">Bank account number</label>
        <input id="bank_account_number" name="bank_account_number" maxlength="34" value="{{ old('bank_account_number', $vendor?->bank_account_number) }}" autocomplete="off" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('bank_account_number') border-red-400 @enderror">
        @error('bank_account_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p class="mt-1 text-xs text-[#898989]">Stored as entered, including leading zeros. The vendor page shows it masked.</p>
    </div>
    <div class="md:col-span-2">
        <label for="notes" class="mb-1.5 block text-sm font-semibold">Notes</label>
        <textarea id="notes" name="notes" rows="3" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm">{{ old('notes', $vendor?->notes) }}</textarea>
    </div>
    @include('admin.inventory._active-field', ['active' => $vendor?->is_active])
</div>
