<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Frontend Tester',
                'email' => 'frontend-test-1782558496@test.local',
                'role' => 'admin',
            ],
            [
                'name' => 'Methodologist Tester',
                'email' => 'methodologist@test.local',
                'role' => 'methodologist',
            ],
            [
                'name' => 'User Tester',
                'email' => 'user@test.local',
                'role' => 'user',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password123'),
                    'role' => $user['role'],
                ]
            );
        }
    }
}
