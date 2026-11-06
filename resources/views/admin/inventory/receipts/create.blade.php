@extends('layouts.admin')

@section('title', 'New Goods Receipt')
@section('page-title', 'Goods Receipts')
@section('page-subtitle', 'New receipt')

@section('content')
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <form method="POST" action="{{ route('admin.inventory.receipts.store') }}">
            @csrf
            @include('admin.inventory.receipts._form')
            <div class="mt-6 flex flex-wrap gap-3">
                <button name="action" value="save" class="rounded-md border border-[#dfdfdf] px-4 py-2.5 text-sm font-semibold">Save draft</button>
                <button name="action" value="post" class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Post receipt</button>
                <a href="{{ route('admin.inventory.receipts.index') }}" class="rounded-md px-4 py-2.5 text-sm font-semibold">Cancel</a>
            </div>
        </form>
    </section>
@endsection
