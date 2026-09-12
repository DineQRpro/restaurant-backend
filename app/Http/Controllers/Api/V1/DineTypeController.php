<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DineTypeResource;
use App\Services\DineType\DineTypeService;
use Illuminate\Http\JsonResponse;

class DineTypeController extends Controller
{
    public function __construct(
        private readonly DineTypeService $dineTypeService,
    ) {}

    public function index(): JsonResponse
    {
        $dineTypes = $this->dineTypeService->active();

        return response()->json([
            'success' => true,
            'message' => 'Dine types retrieved successfully.',
            'data' => DineTypeResource::collection($dineTypes)->resolve(),
        ]);
    }
}
