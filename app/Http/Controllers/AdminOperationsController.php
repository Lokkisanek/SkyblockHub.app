<?php

namespace App\Http\Controllers;

use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;

class AdminOperationsController extends Controller
{
    public function refreshHypixelHealth(AdminOperationsService $operations): JsonResponse
    {
        return response()->json([
            'hypixel' => $operations->hypixelApiHealth(forceRefresh: true),
        ]);
    }
}
