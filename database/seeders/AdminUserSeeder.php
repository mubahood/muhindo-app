<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Seeds the owner (super_admin) account — always user #1. */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'mubahood360@gmail.com';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Muhindo Mubaraka',
                'username' => 'admin',
                'password' => Hash::make('111111'),
                'role' => 'super_admin',
                'is_admin' => true,
                'is_active' => true,
                'password_change_required' => false,
            ]
        );

        // Seeders run WithoutModelEvents, so the saved-hook role sync never
        // fires — assign the Spatie role explicitly here.
        $user->syncSpatieRole();

        $this->command->info("AdminUserSeeder: owner account ready — #{$user->id} {$email} / username=admin / password=111111");
    }
}
