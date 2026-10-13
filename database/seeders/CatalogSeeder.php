<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter construction-materials catalog. Idempotent: safe to re-run.
 * Administrators maintain the catalog from the admin dashboard afterwards.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            'Cement & Concrete' => 'Portland cement, ready-mix additives and concrete products.',
            'Roofing Materials' => 'Iron sheets, tiles, ridges and roofing accessories.',
            'Steel & Iron' => 'Reinforcement bars, binding wire, hollow sections and mesh.',
            'Plumbing' => 'Pipes, fittings, tanks and sanitary ware.',
            'Electrical' => 'Cables, conduits, switches and distribution boards.',
            'Timber & Boards' => 'Structural timber, plywood and boards.',
        ])->mapWithKeys(fn ($description, $name) => [
            $name => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description],
            ),
        ]);

        $brands = collect(['Simba Cement', 'Twiga Cement', 'ALAF', 'Plasco'])->mapWithKeys(
            fn ($name) => [$name => Brand::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])],
        );

        $products = [
            ['Simba Cement 42.5R', 'Cement & Concrete', 'Simba Cement', 'Bag (50kg)'],
            ['Twiga Extra 32.5R', 'Cement & Concrete', 'Twiga Cement', 'Bag (50kg)'],
            ['Versatile Roofing Sheets (Gauge 28)', 'Roofing Materials', 'ALAF', 'Piece (3 meters)'],
            ['Twisted Steel Rebar 12mm', 'Steel & Iron', null, 'Piece (12 meters)'],
            ['Twisted Steel Rebar 16mm', 'Steel & Iron', null, 'Piece (12 meters)'],
            ['Binding Wire (Gauge 16)', 'Steel & Iron', null, 'Roll (25kg)'],
            ['PPR Pipe 25mm PN20', 'Plumbing', 'Plasco', 'Piece (4 meters)'],
            ['Twin & Earth Cable 2.5mm²', 'Electrical', null, 'Roll (100 meters)'],
            ['Hardwood Timber 2x4', 'Timber & Boards', null, 'Piece (12 feet)'],
        ];

        foreach ($products as [$name, $category, $brand, $unit]) {
            Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category_id' => $categories[$category]->id,
                    'brand_id' => $brand ? $brands[$brand]->id : null,
                    'unit' => $unit,
                ],
            );
        }
    }
}
