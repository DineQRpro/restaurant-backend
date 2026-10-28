@extends('layouts.admin-auth')

@section('title', 'No dashboard')

@section('content')
    <div class="admin-login-screen flex min-h-screen items-center justify-center px-3 py-10 sm:px-4">
        <div class="w-full max-w-[450px] rounded-[10px] bg-white p-6 text-center shadow-[0_0_37px_rgba(8,21,66,0.08)] sm:p-10">
            <h3 class="mb-2 text-2xl font-semibold text-[#1f2f3e]">No web dashboard</h3>
            <p class="mb-6 text-sm text-[#898989]">This account is signed in, but its role does not have a web dashboard. Waiter and captain accounts use the staff application.</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#03284f]">
                    Sign out
                </button>
            </form>
        </div>
    </div>
@endsection
