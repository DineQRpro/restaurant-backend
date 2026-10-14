<?php

namespace App\Http\Controllers\Counter;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Counter\StartTakeawayOrderRequest;
use App\Services\Order\CounterTakeawayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TakeawayOrderController extends Controller
{
    public function __construct(private readonly CounterTakeawayService $takeaway) {}

    public function create(): View
    {
        return view('counter.takeaway.order', [
            'draft' => $this->takeaway->draft(),
        ]);
    }

    public function store(StartTakeawayOrderRequest $request): RedirectResponse
    {
        $this->takeaway->start(
            $request->string('customer_name')->toString(),
            $request->string('mobile_number')->toString(),
        );

        return redirect()
            ->route('counter.takeaway.menu')
            ->with('success', 'Order successfully created!');
    }

    public function menu(): View|RedirectResponse
    {
        if (! $this->takeaway->hasDraft()) {
            return redirect()->route('counter.takeaway.create');
        }

        return view('counter.takeaway.menu', [
            'draft' => $this->takeaway->draft(),
            'menus' => $this->takeaway->menuItems(),
            'categories' => $this->takeaway->categories(),
            'cartQuantities' => collect($this->takeaway->draft()['items'])->map(fn (array $row): int => (int) $row['quantity']),
            'totalQuantity' => $this->takeaway->totalQuantity(),
        ]);
    }

    public function add(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->addItem($menuItem), 'counter.takeaway.menu');
    }

    public function increase(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changeQuantity($menuItem, 1), 'counter.takeaway.menu');
    }

    public function decrease(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changeQuantity($menuItem, -1), 'counter.takeaway.menu');
    }

    public function cart(): View|RedirectResponse
    {
        if (! $this->takeaway->hasDraft()) {
            return redirect()->route('counter.takeaway.create');
        }

        return view('counter.takeaway.cart', [
            'draft' => $this->takeaway->draft(),
            'lines' => $this->takeaway->lines(),
            'totalQuantity' => $this->takeaway->totalQuantity(),
            'totalAmount' => $this->takeaway->totalAmount(),
        ]);
    }

    public function cartIncrease(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changeQuantity($menuItem, 1), 'counter.takeaway.cart');
    }

    public function cartDecrease(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changeQuantity($menuItem, -1), 'counter.takeaway.cart');
    }

    public function packingIncrease(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changePacking($menuItem, 1), 'counter.takeaway.cart');
    }

    public function packingDecrease(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->changePacking($menuItem, -1), 'counter.takeaway.cart');
    }

    public function remove(int $menuItem): RedirectResponse
    {
        return $this->mutate(fn () => $this->takeaway->removeItem($menuItem), 'counter.takeaway.cart');
    }

    public function place(): RedirectResponse
    {
        try {
            $order = $this->takeaway->place(request()->user());
        } catch (BusinessConflictException $exception) {
            return redirect()
                ->route('counter.takeaway.cart')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('counter.dashboard')
            ->with('success', 'Order '.$order->order_number.' placed.');
    }

    private function mutate(callable $action, string $route): RedirectResponse
    {
        try {
            $action();
        } catch (BusinessConflictException $exception) {
            return redirect()->route($route)->with('error', $exception->getMessage());
        }

        return redirect()->route($route);
    }
}
