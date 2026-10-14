<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Catalog reference data is safe in every environment. Demo accounts
     * (with a published, shared password) are only created locally.
     */
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoAccountsSeeder::class);
        } else {
            $this->command?->warn('Skipping demo accounts outside local/testing. Create an administrator with: php artisan pal:create-admin');
        }
    }
}
