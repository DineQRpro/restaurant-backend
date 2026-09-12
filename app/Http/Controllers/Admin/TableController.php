<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BusinessConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tables\IndexTableRequest;
use App\Http\Requests\Admin\Tables\StoreTableRequest;
use App\Http\Requests\Admin\Tables\UpdateTableRequest;
use App\Http\Requests\Admin\Tables\UpdateTableStatusRequest;
use App\Models\Table;
use App\Services\Table\TableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TableController extends Controller
{
    public function __construct(
        private readonly TableService $tableService,
    ) {}

    public function index(IndexTableRequest $request): View
    {
        return view('admin.tables.index', [
            'tables' => $this->tableService->paginate($request->filters()),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function create(): View
    {
        return view('admin.tables.create');
    }

    public function store(StoreTableRequest $request): RedirectResponse
    {
        $this->tableService->create($this->payload($request));

        return redirect()
            ->route('admin.tables.index')
            ->with('success', 'Table added successfully.');
    }

    public function edit(Table $table): View
    {
        return view('admin.tables.edit', [
            'table' => $table,
        ]);
    }

    public function update(UpdateTableRequest $request, Table $table): RedirectResponse
    {
        $this->tableService->update($table, $this->payload($request));

        return redirect()
            ->route('admin.tables.index')
            ->with('success', 'Table updated successfully.');
    }

    public function updateStatus(UpdateTableStatusRequest $request, Table $table): RedirectResponse
    {
        $this->tableService->updateStatus($table, $request->boolean('is_active'));

        return redirect()
            ->route('admin.tables.index')
            ->with('success', 'Table status updated successfully.');
    }

    public function destroy(Table $table): RedirectResponse
    {
        try {
            $this->tableService->delete($table);
        } catch (BusinessConflictException $e) {
            return redirect()
                ->route('admin.tables.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.tables.index')
            ->with('success', 'Table deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreTableRequest|UpdateTableRequest $request): array
    {
        $data = $request->safe()->all();

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        if (! array_key_exists('sort_order', $data) || $data['sort_order'] === null) {
            $data['sort_order'] = 0;
        }

        return $data;
    }
}
