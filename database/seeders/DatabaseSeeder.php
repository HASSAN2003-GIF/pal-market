<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a test buyer account
        User::firstOrCreate(
            ['email' => 'buyer@palmarket.co.tz'],
            [
                'name' => 'Test Buyer', 
                'password' => bcrypt('password123'), 
                'role' => 'buyer',
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Construction Categories
        $cement = Category::firstOrCreate(
            ['name' => 'Cement & Concrete'],
            ['slug' => Str::slug('Cement & Concrete')]
        );
        $roofing = Category::firstOrCreate(
            ['name' => 'Roofing Materials'],
            ['slug' => Str::slug('Roofing Materials')]
        );
        $steel = Category::firstOrCreate(
            ['name' => 'Steel & Iron'],
            ['slug' => Str::slug('Steel & Iron')]
        );

        // 3. Create Local Brands
        $simba = Brand::firstOrCreate(
            ['name' => 'Simba Cement'],
            ['slug' => Str::slug('Simba Cement')]
        );
        $twiga = Brand::firstOrCreate(
            ['name' => 'Twiga Cement'],
            ['slug' => Str::slug('Twiga Cement')]
        );
        $alaf = Brand::firstOrCreate(
            ['name' => 'ALAF'],
            ['slug' => Str::slug('ALAF')]
        );

        // 4. Create Products
        Product::firstOrCreate(
            ['name' => 'Simba Cement 42.5R'],
            [
                'slug' => Str::slug('Simba Cement 42.5R'),
                'category_id' => $cement->id,
                'brand_id' => $simba->id,
                'unit' => 'Bag (50kg)',
            ]
        );

        Product::firstOrCreate(
            ['name' => 'Twiga Extra 32.5R'],
            [
                'slug' => Str::slug('Twiga Extra 32.5R'),
                'category_id' => $cement->id,
                'brand_id' => $twiga->id,
                'unit' => 'Bag (50kg)',
            ]
        );

        Product::firstOrCreate(
            ['name' => 'Versatile Roofing Sheets (Gauge 28)'],
            [
                'slug' => Str::slug('Versatile Roofing Sheets (Gauge 28)'),
                'category_id' => $roofing->id,
                'brand_id' => $alaf->id,
                'unit' => 'Piece (3 meters)',
            ]
        );

        Product::firstOrCreate(
            ['name' => 'Twisted Steel Rebar 12mm'],
            [
                'slug' => Str::slug('Twisted Steel Rebar 12mm'),
                'category_id' => $steel->id,
                'brand_id' => null,
                'unit' => 'Piece (12 meters)',
            ]
        );
    }
}