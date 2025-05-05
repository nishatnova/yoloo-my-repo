<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultProfilePhoto = 'user.png';
        // List of admins to create
        $admins = [
            [
                'email' => 'nova8123@gmail.com',
                'name' => 'Admin 1',
                'password' => '123456789',
                'role' => 'admin',
            ],
            [
                'email' => 'sadikahmed2258@gmail.com',
                'name' => 'Admin 2',
                'password' => '123456789',
                'role' => 'admin',
            ],
            [
                'email' => 'demoad007@gmail.com',
                'name' => 'Admin',
                'password' => '123456789',
                'role' => 'admin',
            ],
          
        ];

        foreach ($admins as $adminData) {
            $admin = User::firstOrCreate(
                ['email' => $adminData['email']],
                [
                    'name' => $adminData['name'],
                    'password' => Hash::make($adminData['password']),
                    'role' => $adminData['role'],
                    'profile_photo' => $defaultProfilePhoto,
                ]
            );

            if ($admin->wasRecentlyCreated) {
                $this->command->info("Admin {$adminData['name']} created successfully.");
            } else {
                $this->command->info("Admin {$adminData['name']} already exists.");
            }
        }
    }
}
