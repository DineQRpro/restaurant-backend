@extends('layouts.admin')

@section('title', 'Edit '.$receipt->receipt_number)
@section('page-title', 'Goods Receipts')
@section('page-subtitle', 'Edit draft')

@section('content')
    <section class="rounded-xl bg-white p-5 shadow-sm">
        <p class="mb-4 text-sm text-[#898989]">{{ $receipt->receipt_number }}</p>
        <form method="POST" action="{{ route('admin.inventory.receipts.update', $receipt) }}">
            @csrf
            @method('PUT')
            @include('admin.inventory.receipts._form')
            <div class="mt-6 flex flex-wrap gap-3">
                <button name="action" value="save" class="rounded-md border border-[#dfdfdf] px-4 py-2.5 text-sm font-semibold">Save draft</button>
                <button name="action" value="post" class="rounded-md bg-[#04346a] px-4 py-2.5 text-sm font-semibold text-white">Post receipt</button>
                <a href="{{ route('admin.inventory.receipts.show', $receipt) }}" class="rounded-md px-4 py-2.5 text-sm font-semibold">Cancel</a>
            </div>
        </form>
    </section>
@endsection
