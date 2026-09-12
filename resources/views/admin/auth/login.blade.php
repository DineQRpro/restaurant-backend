@extends('layouts.admin-auth')

@section('title', 'Sign in')

@section('content')
    <div class="admin-login-screen flex min-h-screen items-center justify-center px-3 py-10 sm:px-4">
        <div class="w-full max-w-[450px] rounded-[10px] bg-white p-6 shadow-[0_0_37px_rgba(8,21,66,0.08)] sm:p-10">
            <a href="{{ route('admin.login') }}" class="mb-2.5 block text-center">
                <img src="{{ asset('images/admin/versity-logo-white.jpg') }}" alt="Varsity Kitchen" class="inline-block h-auto w-[130px]">
            </a>

            <form method="POST" action="{{ route('admin.login') }}" class="font-['Nunito_Sans',Roboto,sans-serif]" novalidate>
                @csrf
                <h3 class="mb-1 text-center text-2xl font-semibold text-[#1f2f3e]">Sign in to account</h3>
                <p class="mb-6 text-center text-sm text-[#898989]">Enter your username & password to login</p>

                @if ($errors->any())
                    <p class="mb-4 text-sm text-[#dc3545]">{{ $errors->first() }}</p>
                @endif

                <div class="mb-3">
                    <label for="username" class="mb-1.5 block text-[15px] tracking-wide text-[#1f2f3e]">Email Address</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        value="{{ old('username') }}"
                        placeholder="Username"
                        autocomplete="username"
                        autofocus
                        class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]"
                    >
                </div>

                <div class="mb-3">
                    <label for="password" class="mb-1.5 block text-[15px] tracking-wide text-[#1f2f3e]">Password</label>
                    <div class="relative">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 pr-14 text-sm outline-none focus:border-[#04346a]"
                        >
                        <button
                            type="button"
                            id="toggle-password"
                            class="absolute inset-y-0 right-3 text-xs lowercase text-[#04346a]"
                            aria-label="Show password"
                        >show</button>
                    </div>
                </div>

                <label class="mb-4 flex items-center gap-2 text-sm text-[#898989]">
                    <input type="checkbox" name="remember" value="1" class="rounded border-[#dfdfdf] text-[#04346a]">
                    Remember password
                </label>

                <button type="submit" class="w-full rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#03284f]">
                    Sign in
                </button>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('toggle-password')?.addEventListener('click', function () {
            const input = document.getElementById('password');
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            this.textContent = hidden ? 'hide' : 'show';
            this.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
        });
    </script>
@endsection
