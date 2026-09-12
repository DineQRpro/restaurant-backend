@php
    /** @var \App\Models\User|null $staff */
    $staff = $staff ?? null;
    $isSelf = $staff !== null && auth()->user()?->is($staff);
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Name <span class="text-red-600">*</span>
        </label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $staff?->name) }}"
            placeholder="Enter staff name"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('name') border-red-400 @enderror"
        >
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="username" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Username <span class="text-red-600">*</span>
        </label>
        <input
            id="username"
            type="text"
            name="username"
            value="{{ old('username', $staff?->username) }}"
            placeholder="Enter username"
            required
            autocomplete="username"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('username') border-red-400 @enderror"
        >
        @error('username')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Password
            @if ($staff)
                <span class="font-normal text-[#898989]">(leave blank to keep current)</span>
            @else
                <span class="text-red-600">*</span>
            @endif
        </label>
        <input
            id="password"
            type="password"
            name="password"
            placeholder="{{ $staff ? 'Enter a new password only if changing it' : 'Enter password' }}"
            @required(! $staff)
            autocomplete="new-password"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('password') border-red-400 @enderror"
        >
        @error('password')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Confirm Password
            @unless ($staff)
                <span class="text-red-600">*</span>
            @endunless
        </label>
        <input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            placeholder="Confirm password"
            @required(! $staff)
            autocomplete="new-password"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]"
        >
    </div>

    <div>
        <label for="role_id" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">
            Role <span class="text-red-600">*</span>
        </label>
        <select
            id="role_id"
            name="role_id"
            required
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('role_id') border-red-400 @enderror"
        >
            <option value="">Select Role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) old('role_id', $staff?->role_id) === (string) $role->id)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="mobile" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Phone Number</label>
        <input
            id="mobile"
            type="text"
            name="mobile"
            value="{{ old('mobile', $staff?->mobile) }}"
            placeholder="9876543210"
            maxlength="10"
            inputmode="numeric"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('mobile') border-red-400 @enderror"
        >
        @error('mobile')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="mb-1.5 block text-sm font-semibold text-[#1f2f3e]">Email</label>
        <input
            id="email"
            type="email"
            name="email"
            value="{{ old('email', $staff?->email) }}"
            placeholder="example@gmail.com"
            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a] @error('email') border-red-400 @enderror"
        >
        @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-col justify-end">
        @if ($isSelf)
            <p class="text-sm text-[#898989]">Your own account stays active. Another administrator must change this status.</p>
        @else
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-[#1f2f3e]">
                <input type="hidden" name="is_active" value="0">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    class="rounded border-[#dfdfdf] text-[#04346a]"
                    @checked((string) old('is_active', $staff?->is_active ?? true) === '1' || old('is_active', $staff?->is_active ?? true) === true)
                >
                Active
            </label>
        @endif
    </div>
</div>
