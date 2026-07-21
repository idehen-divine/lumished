<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Seed the application's admin user.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@shelfie.test'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => 'Admin@123',
                'status' => 'ACTIVE',
            ]
        );

        $admin->setRole('ADMIN');
    }
}
