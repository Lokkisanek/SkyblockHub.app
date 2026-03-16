<?php

namespace App\Services;

use App\Models\BazaarPrice;
use App\Models\Recipe;
use Illuminate\Support\Collection;

class ArbitrageService
{
    public function __construct(private readonly BazaarMathService $bazaarMathService)
    {
    }

    public function evaluateRecipeProfitability(int $recipeId): ?array
    {
        $recipe = Recipe::find($recipeId);

        if (! $recipe) {
            return null;
        }

        $ingredients = collect($recipe->ingredients_json ?? []);
        $ingredientProductIds = $ingredients->pluck('product_id')->filter()->values();

        $ingredientPrices = BazaarPrice::query()
            ->whereIn('product_id', $ingredientProductIds)
            ->pluck('buy_price', 'product_id');

        $totalCraftCost = 0.0;
        $resolvedIngredients = [];

        foreach ($ingredients as $ingredient) {
            $productId = (string) ($ingredient['product_id'] ?? '');
            $quantity = (int) ($ingredient['quantity'] ?? 0);
            $buyPrice = (float) ($ingredientPrices[$productId] ?? 0);

            $totalCraftCost += $buyPrice * $quantity;
            $resolvedIngredients[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'buy_price' => $buyPrice,
                'total_cost' => $buyPrice * $quantity,
            ];
        }

        $outputPrice = BazaarPrice::query()->where('product_id', $recipe->output_product_id)->first();

        if (! $outputPrice) {
            return [
                'recipe_id' => $recipe->id,
                'output_product_id' => $recipe->output_product_id,
                'output_quantity' => $recipe->output_quantity,
                'ingredients' => $resolvedIngredients,
                'total_craft_cost' => $totalCraftCost,
                'sell_price' => 0.0,
                'net_profit' => 0.0,
                'profit_per_item' => 0.0,
            ];
        }

        $grossSellValue = (float) $outputPrice->sell_price * (int) $recipe->output_quantity;
        $netProfit = $this->bazaarMathService->calculateMargin($totalCraftCost, $grossSellValue);

        return [
            'recipe_id' => $recipe->id,
            'output_product_id' => $recipe->output_product_id,
            'output_quantity' => $recipe->output_quantity,
            'ingredients' => $resolvedIngredients,
            'total_craft_cost' => $totalCraftCost,
            'sell_price' => (float) $outputPrice->sell_price,
            'net_profit' => $netProfit,
            'profit_per_item' => $recipe->output_quantity > 0 ? $netProfit / $recipe->output_quantity : 0.0,
        ];
    }

    public function topProfitableRecipes(int $limit = 50): Collection
    {
        return Recipe::query()
            ->get()
            ->map(fn (Recipe $recipe) => $this->evaluateRecipeProfitability($recipe->id))
            ->filter()
            ->sortByDesc('net_profit')
            ->take($limit)
            ->values();
    }
}
