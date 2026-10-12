<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Receipts\IndexReceiptRequest;
use App\Http\Requests\Admin\Receipts\StoreReceiptRequest;
use App\Http\Requests\Admin\Receipts\UpdateReceiptRequest;
use App\Models\Receipt;
use App\Services\Receipt\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {}

    public function index(IndexReceiptRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.receipts.index', [
            'receipts' => $this->receiptService->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.receipts.create', [
            'receipt' => null,
        ]);
    }

    public function store(StoreReceiptRequest $request): RedirectResponse
    {
        $this->receiptService->create($request->payload(), $request->itemsPayload());

        return redirect()
            ->route('admin.receipts.index')
            ->with('success', 'Receipt saved successfully.');
    }

    public function edit(Receipt $receipt): View
    {
        $receipt->load(['items']);

        return view('admin.receipts.edit', [
            'receipt' => $receipt,
        ]);
    }

    public function update(UpdateReceiptRequest $request, Receipt $receipt): RedirectResponse
    {
        $this->receiptService->update($receipt, $request->payload(), $request->itemsPayload());

        return redirect()
            ->route('admin.receipts.index')
            ->with('success', 'Receipt updated successfully.');
    }

    public function print(Receipt $receipt): View
    {
        return view('admin.receipts.print', $this->receiptService->printViewData($receipt));
    }
}
