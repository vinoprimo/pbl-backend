<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::factory()->create([
            'username' => 'Super Admin',
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPERADMIN,
            'no_hp' => '081234567890',
            'tanggal_lahir' => '1990-01-01',
            'is_verified' => true,
            'is_active' => true,
            'is_deleted' => false
        ]);

        // Admin
        User::factory()->create([
            'username' => 'Admin',
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_ADMIN,
            'no_hp' => '081234567891',
            'tanggal_lahir' => '1991-01-01',
            'is_verified' => true,
            'is_active' => true,
            'is_deleted' => false
        ]);

        // Regular User
        User::factory()->create([
            'username' => 'User',
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
            'no_hp' => '081234567892',
            'tanggal_lahir' => '1992-01-01',
            'is_verified' => true,
            'is_active' => true,
            'is_deleted' => false
        ]);

        // // Create 10 random users
        // $timestamp = time();
        // for ($i = 1; $i <= 10; $i++) {
        //     User::factory()->create([
        //         'username' => 'user'.$i.'_'.$timestamp,
        //         'name' => 'Random User '.$i,
        //         'email' => 'user'.$i.'_'.$timestamp.'@example.com',
        //         'password' => Hash::make('password'),
        //         'role' => User::ROLE_USER,
        //         'no_hp' => '08123456'.str_pad((7892 + $i), 4, '0', STR_PAD_LEFT),
        //         'tanggal_lahir' => date('Y-m-d', strtotime('1990-01-01 + '.$i.' years')),
        //         'is_verified' => true,
        //         'is_active' => true,
        //         'is_deleted' => false
        //     ]);
        // }
    }
}