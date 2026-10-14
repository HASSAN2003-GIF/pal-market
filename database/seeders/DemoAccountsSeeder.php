<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local demo accounts sharing the password "password123".
 * Refuses to run in production.
 */
class DemoAccountsSeeder extends Seeder
{
    public const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo accounts must never be seeded in production.');
        }

        $admin = $this->user('admin@palmarket.test', 'PAL Admin', 'admin');

        $buyer = $this->user('buyer@palmarket.test', 'Amina Juma', 'buyer');
        $buyer->buyerProfile()->firstOrCreate([], [
            'business_name' => 'Juma Builders (demo)',
            'business_type' => 'Contractor',
        ]);

        $supplierUser = $this->user('supplier@palmarket.test', 'Baraka Mushi', 'supplier');
        $supplier = $supplierUser->supplier()->firstOrCreate([], [
            'business_name' => 'Mushi Hardware (demo)',
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        $location = $supplier->locations()->firstOrCreate(['name' => 'Main yard'], [
            'address' => 'Demo address',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'is_primary' => true,
        ]);

        foreach (Product::query()->orderBy('id')->limit(4)->get() as $index => $product) {
            $supplierProduct = $supplier->supplierProducts()->firstOrCreate(['product_id' => $product->id]);

            $supplierProduct->prices()->firstOrCreate([], [
                'price' => [17500, 16800, 32000, 24500][$index] ?? 10000,
                'currency' => 'TZS',
                'unit' => $product->unit,
            ]);

            $supplierProduct->inventories()->firstOrCreate(
                ['supplier_location_id' => $location->id],
                ['quantity' => 250, 'low_stock_threshold' => 20],
            );
        }

        $this->command?->info('Demo accounts: admin@, buyer@, supplier@palmarket.test / '.self::PASSWORD);
    }

    private function user(string $email, string $name, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => self::PASSWORD]);
        $user->forceFill(['role' => $role, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        return $user;
    }
}
