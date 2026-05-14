<?php

namespace Database\Seeders;

use App\Services\HypixelApiProxy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NpcPricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Comprehensive NPC sell prices for SkyBlock items
     */
    public function run(): void
    {
        // Preferred source: official Hypixel items resource (contains npc_sell_price).
        $npcPrices = $this->loadNpcPricesFromHypixel();

        // Fallback source: local JSON mapping.
        if ($npcPrices === []) {
            $jsonPath = database_path('data/npc_prices.json');
            if (! is_file($jsonPath)) {
                $this->command->error('NPC prices JSON not found at: '.$jsonPath);

                return;
            }

            $decoded = json_decode((string) file_get_contents($jsonPath), true);
            if (! is_array($decoded)) {
                $this->command->error('NPC prices JSON is invalid.');

                return;
            }

            $npcPrices = array_filter(
                $decoded,
                fn ($price) => is_numeric($price) && (float) $price > 0
            );
        }

        // Get all existing products.
        $allProducts = DB::table('bazaar_products')->get();

        $updated = 0;
        $skipped = 0;

        foreach ($allProducts as $product) {
            $productId = $product->product_id;

            // Direct match
            if (isset($npcPrices[$productId])) {
                DB::table('bazaar_products')
                    ->where('product_id', $productId)
                    ->update(['npc_sell_price' => $npcPrices[$productId]]);
                $updated++;
            }
            // Pattern matching for variants (e.g., LOG, LOG:1, LOG:2)
            else {
                $baseId = explode(':', $productId)[0];

                // Try base pattern (e.g., ENCHANTED_DIAMOND for all variants)
                if (isset($npcPrices[$baseId])) {
                    DB::table('bazaar_products')
                        ->where('product_id', $productId)
                        ->update(['npc_sell_price' => $npcPrices[$baseId]]);
                    $updated++;
                } else {
                    // Keep unknown NPC prices as zero in DB.
                    DB::table('bazaar_products')
                        ->where('product_id', $productId)
                        ->update(['npc_sell_price' => 0]);
                    $skipped++;
                }
            }
        }

        $this->command->info("NPC prices seeded: $updated updated, $skipped without NPC prices");
    }

    /**
     * @return array<string, float>
     */
    private function loadNpcPricesFromHypixel(): array
    {
        try {
            $data = app(HypixelApiProxy::class)->getItems();

            if (! $data || ! ($data['success'] ?? false)) {
                return [];
            }

            $items = (array) ($data['items'] ?? []);
            $result = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');
                $npcSellPrice = (float) ($item['npc_sell_price'] ?? 0);

                if ($id === '' || $npcSellPrice <= 0) {
                    continue;
                }

                $result[$id] = $npcSellPrice;
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }
}
