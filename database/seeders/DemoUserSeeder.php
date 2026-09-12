<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['role' => 'admin', 'name' => 'Admin', 'username' => 'admin', 'email' => 'admin@restaurant.test', 'mobile' => '9876500001'],
            ['role' => 'waiter', 'name' => 'Demo Waiter', 'username' => 'waiter', 'email' => 'waiter@restaurant.test', 'mobile' => '9876500002'],
            ['role' => 'captain', 'name' => 'Demo Captain', 'username' => 'captain', 'email' => 'captain@restaurant.test', 'mobile' => '9876500003'],
            ['role' => 'counter', 'name' => 'Demo Counter', 'username' => 'counter', 'email' => 'counter@restaurant.test', 'mobile' => '9876500004'],
        ];

        foreach ($users as $user) {
            $role = Role::query()->where('slug', $user['role'])->firstOrFail();

            User::query()->firstOrCreate(
                ['username' => $user['username']],
                [
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'mobile' => $user['mobile'],
                    'password' => 'password',
                    'role_id' => $role->id,
                    'is_active' => true,
                ],
            );
        }
    }
}
