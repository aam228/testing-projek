<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('web_configs')->insert([
            'config_name' => 'app_title',
            'config_value' => 'Topscore Management System'
        ]);

        DB::table('web_configs')->insert([
            'config_name' => 'app_logo',
            'config_value' => 'topscore.png'
        ]);

        DB::table('web_configs')->insert([
            'config_name' => 'pos_prefix',
            'config_value' => 'INV'
        ]);

        DB::table('web_configs')->insert([
            'config_name' => 'default_setup_bin',
            'config_value' => 'TI01'
        ]);
    }
}
