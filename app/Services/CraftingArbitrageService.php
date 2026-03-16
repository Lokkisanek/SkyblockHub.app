<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class CraftingArbitrageService
{
    /**
     * Calculate arbitrage profit for every recipe in the database.
     * Applies the 1.25% Hypixel Bazaar sell tax.
     *
     * @return array<int, array<string, mixed>>
     */
    public function calculateAllRecipes(): array
    {
        $recipes = Recipe::all();

        if ($recipes->isEmpty()) {
            return [];
        }

        // Collect every product_id we need prices/names for
        $allIds = collect();
        foreach ($recipes as $recipe) {
            $allIds->push($recipe->output_product_id);
            foreach ((array) $recipe->ingredients_json as $ingredient) {
                $allIds->push($ingredient['item_id'] ?? null);
            }
        }

        $uniqueIds = $allIds->filter()->unique()->values()->all();

        // Single-query bulk fetches
        $prices = DB::table('bazaar_prices')
            ->whereIn('product_id', $uniqueIds)
            ->get()
            ->keyBy('product_id');

        $products = DB::table('bazaar_products')
            ->whereIn('product_id', $uniqueIds)
            ->get()
            ->keyBy('product_id');

        $results = [];

        foreach ($recipes as $recipe) {
            $totalCraftCost  = 0.0;
            $ingredientDetails = [];
            $allAvailable    = true;

            foreach ((array) $recipe->ingredients_json as $ingredient) {
                $itemId = $ingredient['item_id'] ?? null;
                $qty    = (int) ($ingredient['quantity'] ?? 1);

                if (! $itemId) {
                    continue;
                }

                $priceRow  = $prices->get($itemId);
                $buyPrice  = $priceRow ? (float) $priceRow->buy_price : 0.0;
                $lineCost  = $buyPrice * $qty;
                $totalCraftCost += $lineCost;

                if (! $priceRow) {
                    $allAvailable = false;
                }

                $productRow = $products->get($itemId);

                $ingredientDetails[] = [
                    'item_id'    => $itemId,
                    'name'       => $productRow ? $productRow->name : $itemId,
                    'quantity'   => $qty,
                    'unit_price' => round($buyPrice, 2),
                    'total_cost' => round($lineCost, 2),
                    'available'  => $priceRow !== null,
                ];
            }

            $outputPrice    = $prices->get($recipe->output_product_id);
            $sellPrice      = $outputPrice ? (float) $outputPrice->sell_price : 0.0;
            $outputProduct  = $products->get($recipe->output_product_id);
            $name           = $outputProduct ? $outputProduct->name : $recipe->output_product_id;

            // Apply 1.25% Hypixel tax on the sell-side revenue
            $taxedRevenue   = $sellPrice * 0.9875 * $recipe->output_quantity;
            $netProfit      = $taxedRevenue - $totalCraftCost;
            $marginPercent  = $totalCraftCost > 0 ? ($netProfit / $totalCraftCost) * 100 : 0.0;

            $results[] = [
                'product_id'      => $recipe->output_product_id,
                'name'            => $name,
                'output_quantity' => $recipe->output_quantity,
                'category'        => $recipe->category,
                'sell_price'      => round($sellPrice, 2),
                'craft_cost'      => round($totalCraftCost, 2),
                'taxed_revenue'   => round($taxedRevenue, 2),
                'net_profit'      => round($netProfit, 2),
                'margin_percent'  => round($marginPercent, 2),
                'all_available'   => $allAvailable,
                'ingredients'     => $ingredientDetails,
            ];
        }

        // Default sort: best net profit first
        usort($results, static fn ($a, $b) => $b['net_profit'] <=> $a['net_profit']);

        return $results;
    }
}
