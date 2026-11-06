@extends('layouts.admin')
@section('title', 'Add Unit of Measure')
@section('page-title', 'Units of Measure')
@section('page-subtitle', 'Add Unit')
@section('content')
    <section class="overflow-hidden rounded-xl bg-white shadow-sm">
        <div class="border-b border-[#eef1f5] px-5 py-4"><h2 class="text-lg font-bold">Add Unit of Measure</h2></div>
        <div class="p-5">
            <form method="POST" action="{{ route('admin.inventory.units.store') }}" class="rounded-lg border border-[#eef1f5] p-5">
                @csrf
                @include('admin.inventory.units._form')
                <div class="mt-6 flex gap-3">
                    <button class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Submit</button>
                    <a href="{{ route('admin.inventory.units.index') }}" class="rounded-md border border-[#dfdfdf] px-4 py-2.5 text-sm font-semibold">Cancel</a>
                </div>
            </form>
        </div>
    </section>
@endsection
