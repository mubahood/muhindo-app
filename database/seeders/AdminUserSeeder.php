<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the owner (super_admin) account. Uses the real muhindomubaraka.com
 * password hash when present (so the existing password keeps working after
 * the migration to this codebase); otherwise generates a temporary one.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'mubahood360@gmail.com';
        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $this->command->info('AdminUserSeeder: owner account already exists — password left untouched.');
            $user->syncSpatieRole();

            return;
        }

        $preservedHashPath = base_path('_db_seed/owner_password_hash.txt');
        $preservedHash = is_file($preservedHashPath) ? trim(file_get_contents($preservedHashPath)) : null;

        $password = $preservedHash ? null : Str::password(16);

        $user = User::create([
            'name' => 'Muhindo Mubaraka',
            'username' => 'muhindo',
            'email' => $email,
            'password' => $preservedHash ?: Hash::make($password),
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'password_change_required' => $preservedHash === null,
        ]);

        // Seeders run WithoutModelEvents, so the saved-hook role sync never
        // fires — assign the Spatie role explicitly here.
        $user->syncSpatieRole();

        if ($preservedHash) {
            $this->command->info("AdminUserSeeder: owner account created — {$email} (existing password preserved).");
        } else {
            $this->command->warn("AdminUserSeeder: owner account created — {$email} / {$password}");
            $this->command->warn('AdminUserSeeder: temporary password, change required at first login.');
        }
    }
}
