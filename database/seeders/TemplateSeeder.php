<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            ['name' => 'template_1', 'title' => 'Rose Wedding Invitation Template', 'price' => 30],
            ['name' => 'template_2', 'title' => 'Floral Wedding Invitation Template', 'price' => 30],
            ['name' => 'template_3', 'title' => 'Luxury Wedding Invitation Template', 'price' => 30],
            ['name' => 'template_4', 'title' => 'Minimalist Green Wedding', 'price' => 30],
        ];

        DB::table('templates')->insert($templates);
    }
}
