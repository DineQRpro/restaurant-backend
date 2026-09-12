@extends('layouts.admin')

@section('title', 'Update Staff')
@section('page-title', 'Restaurant Staff')
@section('page-subtitle', 'Update restaurant staff')

@section('content')
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('admin.users.index') }}" class="font-semibold text-[#04346a] hover:underline">Users</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Update Staff</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold text-[#1f2f3e]">Update restaurant staff</h2>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.users.update', $staff) }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @method('PUT')
                @include('admin.users._form', ['staff' => $staff])
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Submit
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2.5 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </section>
@endsection
