<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Menu\IndexMenuCategoryRequest;
use App\Http\Requests\Api\V1\Menu\StoreMenuCategoryRequest;
use App\Http\Requests\Api\V1\Menu\UpdateMenuCategoryRequest;
use App\Http\Requests\Api\V1\Menu\UpdateMenuCategoryStatusRequest;
use App\Http\Resources\Api\V1\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Services\Menu\MenuCategoryService;
use Illuminate\Http\JsonResponse;

class MenuCategoryController extends Controller
{
    public function __construct(
        private readonly MenuCategoryService $menuCategoryService,
    ) {}

    public function index(IndexMenuCategoryRequest $request): JsonResponse
    {
        $filters = $request->validated();

        if ($request->exists('is_active')) {
            $filters['is_active'] = $request->boolean('is_active');
        }

        $categories = $this->menuCategoryService->paginate($filters);

        return response()->json([
            'success' => true,
            'message' => 'Menu categories retrieved successfully.',
            'data' => MenuCategoryResource::collection($categories)->resolve(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ],
        ]);
    }

    public function store(StoreMenuCategoryRequest $request): JsonResponse
    {
        $category = $this->menuCategoryService->create($request->validated())->load('menuType');

        return response()->json([
            'success' => true,
            'message' => 'Menu category created successfully.',
            'data' => new MenuCategoryResource($category),
        ], 201);
    }

    public function show(MenuCategory $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Menu category retrieved successfully.',
            'data' => new MenuCategoryResource($category->load('menuType')),
        ]);
    }

    public function update(UpdateMenuCategoryRequest $request, MenuCategory $category): JsonResponse
    {
        $category = $this->menuCategoryService->update($category, $request->validated())->load('menuType');

        return response()->json([
            'success' => true,
            'message' => 'Menu category updated successfully.',
            'data' => new MenuCategoryResource($category),
        ]);
    }

    public function updateStatus(UpdateMenuCategoryStatusRequest $request, MenuCategory $category): JsonResponse
    {
        $category = $this->menuCategoryService->updateStatus(
            $category,
            $request->boolean('is_active'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Menu category status updated successfully.',
            'data' => new MenuCategoryResource($category->load('menuType')),
        ]);
    }

    public function destroy(MenuCategory $category): JsonResponse
    {
        $this->menuCategoryService->delete($category);

        return response()->json([
            'success' => true,
            'message' => 'Menu category deleted successfully.',
            'data' => null,
        ]);
    }
}
