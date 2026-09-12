<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Menu\IndexMenuItemRequest;
use App\Http\Requests\Api\V1\Menu\StoreMenuItemRequest;
use App\Http\Requests\Api\V1\Menu\UpdateMenuItemRequest;
use App\Http\Requests\Api\V1\Menu\UpdateMenuItemStatusRequest;
use App\Http\Resources\Api\V1\MenuItemResource;
use App\Models\MenuItem;
use App\Services\Menu\MenuItemService;
use Illuminate\Http\JsonResponse;

class MenuItemController extends Controller
{
    public function __construct(
        private readonly MenuItemService $menuItemService,
    ) {}

    public function index(IndexMenuItemRequest $request): JsonResponse
    {
        $filters = $request->validated();

        if ($request->exists('is_available')) {
            $filters['is_available'] = $request->boolean('is_available');
        }

        if ($request->exists('is_chef_special')) {
            $filters['is_chef_special'] = $request->boolean('is_chef_special');
        }

        $items = $this->menuItemService->paginate($filters);

        return response()->json([
            'success' => true,
            'message' => 'Menu items retrieved successfully.',
            'data' => MenuItemResource::collection($items)->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuItemService->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu item created successfully.',
            'data' => new MenuItemResource($item),
        ], 201);
    }

    public function show(MenuItem $item): JsonResponse
    {
        $item->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Menu item retrieved successfully.',
            'data' => new MenuItemResource($item),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $item): JsonResponse
    {
        $item = $this->menuItemService->update($item, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Menu item updated successfully.',
            'data' => new MenuItemResource($item),
        ]);
    }

    public function updateStatus(UpdateMenuItemStatusRequest $request, MenuItem $item): JsonResponse
    {
        $item = $this->menuItemService->updateStatus(
            $item,
            $request->boolean('is_available'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Menu item availability updated successfully.',
            'data' => new MenuItemResource($item),
        ]);
    }

    public function destroy(MenuItem $item): JsonResponse
    {
        $this->menuItemService->delete($item);

        return response()->json([
            'success' => true,
            'message' => 'Menu item deleted successfully.',
            'data' => null,
        ]);
    }
}
