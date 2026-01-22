<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin account if it doesn't exist
        $adminEmail = 'admin@bvicustoms.com';
        
        if (!User::where('email', $adminEmail)->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => $adminEmail,
                'password' => Hash::make('admin123'), // Change this password after first login!
                'role' => 'admin',
                'is_individual' => true,
                'onboarding_completed' => true,
            ]);

            $this->command->info('Super Admin account created successfully!');
            $this->command->warn('Email: ' . $adminEmail);
            $this->command->warn('Password: admin123');
            $this->command->error('⚠️  IMPORTANT: Change this password after first login!');
        } else {
            $this->command->info('Super Admin account already exists.');
        }
    }
}
