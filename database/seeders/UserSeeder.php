<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('users')->insert([
            'u_name' => 'Administrator',
            'u_email' => 'admin@admin.com',
            'password' => '$2y$10$DqqY9qu6STD26dq7oXjyD.Te7rdXTu2G5Qi71C96NEOFOYrBzjN0y',
            'u_address' => 'Administrator Address',
            'u_phone' => '081234567890',
            'u_delete' => '0',
        ]);
    }
}
