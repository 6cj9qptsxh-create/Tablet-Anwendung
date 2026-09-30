<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Sagt Laravel: "Wenn du seedest, ruf bitte unseren Import auf!"
        $this->call([
            ProductImportSeeder::class,
        ]);
    }
}