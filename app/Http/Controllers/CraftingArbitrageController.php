<?php

namespace App\Http\Controllers;

use App\Services\CraftingArbitrageService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CraftingArbitrageController extends Controller
{
    public function index(CraftingArbitrageService $service): Response
    {
        $recipes = $service->calculateAllRecipes();

        $categories = collect($recipes)
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return Inertia::render('Crafting/Index', [
            'recipes'    => $recipes,
            'categories' => $categories,
        ]);
    }

    public function api(CraftingArbitrageService $service): JsonResponse
    {
        return response()->json($service->calculateAllRecipes());
    }
}
