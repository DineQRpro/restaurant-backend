@extends('layouts.admin')

@section('title', 'Add New Table')
@section('page-title', 'Restaurant Table List')
@section('page-subtitle', 'Add New Table')

@section('content')
    <nav class="mb-4 text-sm text-[#898989]" aria-label="Breadcrumb">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('admin.dashboard') }}" class="font-semibold text-[#04346a] hover:underline">Dashboard</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('admin.tables.index') }}" class="font-semibold text-[#04346a] hover:underline">Tables</a></li>
            <li aria-hidden="true">/</li>
            <li class="font-semibold text-[#1f2f3e]">Add New Table</li>
        </ol>
    </nav>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4">
            <h2 class="text-lg font-bold text-[#1f2f3e]">Add new restaurant Table</h2>
        </div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.tables.store') }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @include('admin.tables._form')
                <div class="mt-6 flex flex-wrap gap-3">
                    <button type="submit" class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#03284f]">
                        Submit
                    </button>
                    <a href="{{ route('admin.tables.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2.5 text-sm font-semibold text-[#1f2f3e] hover:bg-[#f4f6f9]">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </section>
@endsection
