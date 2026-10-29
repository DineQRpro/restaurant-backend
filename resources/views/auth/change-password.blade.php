@extends('layouts.admin-auth')

@section('title', $title)

@section('content')
    <div class="admin-login-screen flex min-h-screen items-center justify-center px-3 py-10 sm:px-4">
        <div class="w-full max-w-[450px] rounded-[10px] bg-white p-6 shadow-[0_0_37px_rgba(8,21,66,0.08)] sm:p-10">
            <a href="{{ $backUrl }}" class="mb-2.5 block text-center">
                <img src="{{ asset('images/admin/versity-logo-white.jpg') }}" alt="Varsity Kitchen" class="inline-block h-auto w-[130px]">
            </a>

            <form method="POST" action="{{ $action }}" class="font-['Nunito_Sans',Roboto,sans-serif]" novalidate>
                @csrf
                <h3 class="mb-1 text-center text-2xl font-semibold text-[#1f2f3e]">{{ $title }}</h3>
                <p class="mb-6 text-center text-sm text-[#898989]">{{ $intro }}</p>

                @if ($errors->any())
                    <p class="mb-4 text-sm text-[#dc3545]">{{ $errors->first() }}</p>
                @endif

                @if ($requireUsername)
                    <div class="mb-3">
                        <label for="username" class="mb-1.5 block text-[15px] text-[#1f2f3e]">Username</label>
                        <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 text-sm outline-none focus:border-[#04346a]">
                    </div>
                @endif

                @foreach ([
                    'current_password' => ['Current password', 'current-password'],
                    'password' => ['New password', 'new-password'],
                    'password_confirmation' => ['Confirm new password', 'new-password'],
                ] as $name => [$label, $autocomplete])
                    <div class="mb-3">
                        <label for="{{ $name }}" class="mb-1.5 block text-[15px] text-[#1f2f3e]">{{ $label }}</label>
                        <div class="relative">
                            <input
                                id="{{ $name }}"
                                name="{{ $name }}"
                                type="password"
                                autocomplete="{{ $autocomplete }}"
                                class="w-full rounded-md border border-[#dfdfdf] px-3 py-2.5 pr-14 text-sm outline-none focus:border-[#04346a]"
                            >
                            <button
                                type="button"
                                class="password-toggle absolute inset-y-0 right-3 text-xs lowercase text-[#04346a]"
                                data-target="{{ $name }}"
                                aria-label="Show {{ strtolower($label) }}"
                            >show</button>
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="mt-1 w-full rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#03284f]">
                    Change password
                </button>
            </form>
            <a href="{{ $backUrl }}" class="mt-4 block text-center text-sm text-[#04346a]">{{ $backLabel }}</a>
        </div>
    </div>

    <script>
        document.querySelectorAll('.password-toggle').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = document.getElementById(button.dataset.target);
                if (! input) {
                    return;
                }
                const hidden = input.type === 'password';
                input.type = hidden ? 'text' : 'password';
                button.textContent = hidden ? 'hide' : 'show';
                button.setAttribute('aria-label', (hidden ? 'Hide ' : 'Show ') + button.dataset.target.replaceAll('_', ' '));
            });
        });
    </script>
@endsection
