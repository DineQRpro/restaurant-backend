@extends('layouts.admin-auth')

@section('title', $title)

@section('content')
    <div class="admin-login-screen flex min-h-screen items-center justify-center px-3 py-10 sm:px-4">
        <div class="w-full max-w-[450px] rounded-[10px] bg-white p-6 shadow-[0_0_37px_rgba(8,21,66,0.08)] sm:p-10">
            <form method="POST" action="{{ $action }}" class="font-['Nunito_Sans',Roboto,sans-serif]" novalidate>
                @csrf
                <h3 class="mb-1 text-center text-2xl font-semibold text-[#1f2f3e]">{{ $title }}</h3>
                <p class="mb-6 text-center text-sm text-[#898989]">Enter the temporary password, then choose a new one.</p>

                @if ($errors->any())
                    <p class="mb-4 text-sm text-[#dc3545]">{{ $errors->first() }}</p>
                @endif

                <div class="mb-3">
                    <label for="username" class="mb-1.5 block text-[15px] text-[#1f2f3e]">Username</label>
                    <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
                </div>
                <div class="mb-3">
                    <label for="current_password" class="mb-1.5 block text-[15px] text-[#1f2f3e]">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
                </div>
                <div class="mb-3">
                    <label for="password" class="mb-1.5 block text-[15px] text-[#1f2f3e]">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
                </div>
                <div class="mb-4">
                    <label for="password_confirmation" class="mb-1.5 block text-[15px] text-[#1f2f3e]">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
                </div>
                <button type="submit" class="w-full rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Change password</button>
            </form>
            <a href="{{ $loginUrl }}" class="mt-4 block text-center text-sm text-[#04346a]">Back to sign in</a>
        </div>
    </div>
@endsection
