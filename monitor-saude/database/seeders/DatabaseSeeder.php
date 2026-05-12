<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        DB::table('cities')->insert([
            'name' => 'Ourinhos',
            'ibge_code' => 3534708,
            'uf' => 'SP',
            'location' => DB::raw("ST_GeogFromText('POINT(-49.8711 -22.9789)')"),
        ]);
    }
}
