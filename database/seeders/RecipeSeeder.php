<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Seeder;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        Recipe::query()->delete();

        $recipes = [
            // ── Mining: base → enchanted ─────────────────────────────────────
            ['output' => 'ENCHANTED_COAL',           'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'COAL',                 'quantity' => 160]]],
            ['output' => 'ENCHANTED_COAL_BLOCK',      'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_COAL',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_IRON',            'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'IRON_INGOT',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_IRON_BLOCK',      'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_IRON',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_GOLD',            'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'GOLD_INGOT',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_GOLD_BLOCK',      'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_GOLD',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_DIAMOND',         'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'DIAMOND',               'quantity' => 160]]],
            ['output' => 'ENCHANTED_DIAMOND_BLOCK',   'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_DIAMOND',     'quantity' => 160]]],
            ['output' => 'ENCHANTED_EMERALD',         'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'EMERALD',               'quantity' => 160]]],
            ['output' => 'ENCHANTED_EMERALD_BLOCK',   'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_EMERALD',     'quantity' => 160]]],
            ['output' => 'ENCHANTED_REDSTONE',        'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'REDSTONE',              'quantity' => 160]]],
            ['output' => 'ENCHANTED_REDSTONE_BLOCK',  'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_REDSTONE',    'quantity' => 160]]],
            ['output' => 'ENCHANTED_QUARTZ',          'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'QUARTZ',                'quantity' => 160]]],
            ['output' => 'ENCHANTED_QUARTZ_BLOCK',    'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_QUARTZ',      'quantity' => 160]]],
            ['output' => 'ENCHANTED_OBSIDIAN',        'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'OBSIDIAN',              'quantity' => 160]]],
            ['output' => 'ENCHANTED_GLOWSTONE_DUST',  'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'GLOWSTONE_DUST',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_GLOWSTONE',       'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_GLOWSTONE_DUST', 'quantity' => 160]]],
            ['output' => 'ENCHANTED_LAPIS_LAZULI',    'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'INK_SACK:4',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_LAPIS_LAZULI_BLOCK','qty'=> 1,'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_LAPIS_LAZULI','quantity' => 160]]],
            ['output' => 'ENCHANTED_COBBLESTONE',     'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'COBBLESTONE',           'quantity' => 160]]],
            ['output' => 'ENCHANTED_ICE',             'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ICE',                   'quantity' => 160]]],
            ['output' => 'ENCHANTED_PACKED_ICE',      'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_ICE',         'quantity' => 160]]],
            ['output' => 'ENCHANTED_SAND',            'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'SAND',                  'quantity' => 160]]],
            ['output' => 'ENCHANTED_CLAY_BALL',       'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'CLAY_BALL',             'quantity' => 160]]],
            ['output' => 'ENCHANTED_CLAY_BLOCK',      'qty' => 1, 'cat' => 'Mining',   'ing' => [['item_id' => 'ENCHANTED_CLAY_BALL',   'quantity' => 160]]],

            // ── Farming ───────────────────────────────────────────────────────
            ['output' => 'ENCHANTED_WHEAT',           'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'WHEAT',                 'quantity' => 160]]],
            ['output' => 'ENCHANTED_HAY_BALE',        'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'ENCHANTED_WHEAT',       'quantity' => 160]]],
            ['output' => 'ENCHANTED_CARROT',          'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'CARROT_ITEM',           'quantity' => 160]]],
            ['output' => 'ENCHANTED_POTATO',          'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'POTATO_ITEM',           'quantity' => 160]]],
            ['output' => 'ENCHANTED_PUMPKIN',         'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'PUMPKIN',               'quantity' => 160]]],
            ['output' => 'ENCHANTED_MELON',           'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'MELON',                 'quantity' => 160]]],
            ['output' => 'ENCHANTED_SUGAR_CANE',      'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'SUGAR_CANE',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_NETHER_STALK',    'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'NETHER_STALK',          'quantity' => 160]]],
            ['output' => 'ENCHANTED_CACTUS',          'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'CACTUS',                'quantity' => 160]]],
            ['output' => 'ENCHANTED_CACTUS_GREEN',    'qty' => 1, 'cat' => 'Farming',  'ing' => [['item_id' => 'ENCHANTED_CACTUS',      'quantity' => 160]]],
            ['output' => 'ENCHANTED_MUSHROOM_COLLECTION','qty'=>1,'cat' => 'Farming',  'ing' => [['item_id' => 'MUSHROOM_COLLECTION',   'quantity' => 160]]],

            // ── Combat ────────────────────────────────────────────────────────
            ['output' => 'ENCHANTED_SLIME_BALL',      'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'SLIME_BALL',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_SLIME_BLOCK',     'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'ENCHANTED_SLIME_BALL',  'quantity' => 160]]],
            ['output' => 'ENCHANTED_SPIDER_EYE',      'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'SPIDER_EYE',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_BONE',            'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'BONE',                  'quantity' => 160]]],
            ['output' => 'ENCHANTED_BONE_BLOCK',      'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'ENCHANTED_BONE',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_BONE_MEAL',       'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'ENCHANTED_BONE',        'quantity' => 160]]],
            ['output' => 'ENCHANTED_STRING',          'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'STRING',                'quantity' => 160]]],
            ['output' => 'ENCHANTED_ROTTEN_FLESH',    'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'ROTTEN_FLESH',          'quantity' => 160]]],
            ['output' => 'ENCHANTED_GHAST_TEAR',      'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'GHAST_TEAR',            'quantity' => 160]]],
            ['output' => 'ENCHANTED_MAGMA_CREAM',     'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'MAGMA_CREAM',           'quantity' => 160]]],
            ['output' => 'ENCHANTED_BLAZE_ROD',       'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'BLAZE_ROD',             'quantity' => 160]]],
            ['output' => 'ENCHANTED_BLAZE_POWDER',    'qty' => 1, 'cat' => 'Combat',   'ing' => [['item_id' => 'ENCHANTED_BLAZE_ROD',   'quantity' => 160]]],

            // ── Fishing ───────────────────────────────────────────────────────
            ['output' => 'ENCHANTED_RAW_FISH',        'qty' => 1, 'cat' => 'Fishing',  'ing' => [['item_id' => 'RAW_FISH',              'quantity' => 160]]],
            ['output' => 'ENCHANTED_RAW_SALMON',      'qty' => 1, 'cat' => 'Fishing',  'ing' => [['item_id' => 'RAW_FISH',              'quantity' => 160]]],
        ];

        foreach ($recipes as $r) {
            // Only insert if output product exists in bazaar_products (FK constraint)
            $exists = \Illuminate\Support\Facades\DB::table('bazaar_products')
                ->where('product_id', $r['output'])
                ->exists();

            if (! $exists) {
                continue;
            }

            Recipe::create([
                'output_product_id' => $r['output'],
                'output_quantity'   => $r['qty'],
                'category'          => $r['cat'],
                'ingredients_json'  => $r['ing'],
            ]);
        }

        $count = Recipe::count();
        $this->command->info("Inserted {$count} recipes.");
    }
}
