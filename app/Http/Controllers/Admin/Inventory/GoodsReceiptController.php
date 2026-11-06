<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\GoodsReceiptIndexRequest;
use App\Http\Requests\Admin\Inventory\GoodsReceiptRequest;
use App\Http\Requests\Admin\Inventory\PostGoodsReceiptRequest;
use App\Http\Requests\Admin\Inventory\StockIndexRequest;
use App\Http\Requests\Admin\Inventory\VoidGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\GoodsReceiptPoster;
use App\Services\Inventory\GoodsReceiptService;
use App\Services\Inventory\GoodsReceiptVoider;
use App\Services\Inventory\StockBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $receipts,
        private readonly GoodsReceiptPoster $poster,
        private readonly GoodsReceiptVoider $voider,
        private readonly StockBalanceService $stock,
    ) {}

    public function index(GoodsReceiptIndexRequest $request): View
    {
        return view('admin.inventory.receipts.index', [
            'receipts' => $this->receipts->paginate($request->filters()),
            'filters' => $request->filters(),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(): View
    {
        return view('admin.inventory.receipts.create');
    }

    public function store(GoodsReceiptRequest $request): RedirectResponse
    {
        try {
            $receipt = $this->receipts->create($request->receiptData(), $request->user());

            if ($request->input('action') === 'post') {
                return $this->finishPost($request->user(), $receipt, $request->boolean('confirm_zero_prices'));
            }
        } catch (BusinessConflictException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.receipts.show', $receipt)
            ->with('success', 'Draft goods receipt '.$receipt->receipt_number.' saved.');
    }

    public function show(GoodsReceipt $receipt): View
    {
        $receipt->load(['lines', 'vendor', 'creator', 'poster', 'voider']);

        return view('admin.inventory.receipts.show', ['receipt' => $receipt]);
    }

    public function edit(GoodsReceipt $receipt): View|RedirectResponse
    {
        if ($receipt->status->value !== 'draft') {
            return redirect()
                ->route('admin.inventory.receipts.show', $receipt)
                ->with('error', 'Posted and voided receipts cannot be edited.');
        }

        $receipt->load('lines.item', 'vendor');

        return view('admin.inventory.receipts.edit', ['receipt' => $receipt]);
    }

    public function update(GoodsReceiptRequest $request, GoodsReceipt $receipt): RedirectResponse
    {
        try {
            $receipt = $this->receipts->update($receipt, $request->receiptData());

            if ($request->input('action') === 'post') {
                return $this->finishPost($request->user(), $receipt, $request->boolean('confirm_zero_prices'));
            }
        } catch (BusinessConflictException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.receipts.show', $receipt)
            ->with('success', 'Draft goods receipt updated.');
    }

    public function destroy(GoodsReceipt $receipt): RedirectResponse
    {
        try {
            $this->receipts->delete($receipt);
        } catch (BusinessConflictException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.receipts.index')
            ->with('success', 'Draft goods receipt deleted.');
    }

    public function post(PostGoodsReceiptRequest $request, GoodsReceipt $receipt): RedirectResponse
    {
        return $this->finishPost($request->user(), $receipt, $request->boolean('confirm_zero_prices'));
    }

    public function void(VoidGoodsReceiptRequest $request, GoodsReceipt $receipt): RedirectResponse
    {
        try {
            $this->voider->void($receipt, $request->user(), $request->string('void_reason')->toString());
        } catch (BusinessConflictException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.receipts.show', $receipt)
            ->with('success', 'Goods receipt voided. Reversal movements were added.');
    }

    public function print(GoodsReceipt $receipt): View
    {
        $receipt->load(['lines', 'creator', 'poster', 'voider']);

        return view('admin.inventory.receipts.print', ['receipt' => $receipt]);
    }

    public function items(StockIndexRequest $request): JsonResponse
    {
        return response()->json($this->stock->suggestItems($request->filters()));
    }

    public function vendors(StockIndexRequest $request): JsonResponse
    {
        return response()->json($this->receipts->suggestVendors((string) $request->string('q')));
    }

    private function finishPost(User $user, GoodsReceipt $receipt, bool $confirmZeroPrices): RedirectResponse
    {
        try {
            $result = $this->poster->post($receipt, $user, $confirmZeroPrices);
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('admin.inventory.receipts.show', $receipt)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.inventory.receipts.show', $result['receipt'])
            ->with('success', 'Goods receipt posted.')
            ->with('warnings', $result['warnings']);
    }
}
