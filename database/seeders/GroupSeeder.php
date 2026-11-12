<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('groups')->insert([
            'g_name' => 'administrator',
            'g_description' => 'All Access',
        ]);

        DB::table('groups')->insert([
            'g_name' => 'general',
            'g_description' => 'Specific Access',
        ]);
    }
}
