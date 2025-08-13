<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Claims\Custom;

/**
 * Seeder class responsible for creating the superadmin user and role.
 */
class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This method checks for the existence of a user with the email specified in the
     * environment variable `ADMIN_EMAIL`. If not found, it creates a new user with the
     * name, email, and password specified in the environment, assigns the 'superadmin'
     * role to the user, and handles the operation within a database transaction to ensure
     * consistency. If any part of the transaction fails, it rolls back the changes.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $userEmail = env('ADMIN_EMAIL');
            $userPassword = env('ADMIN_PASSWORD');
            $user = User::where('email', $userEmail)->first();

            if (empty($user)) {
                $user = User::create([
                    'name' => env('ADMIN_NAME'),
                    'email' => $userEmail,
                    'password' => Hash::make($userPassword),
                    'is_activated' => config('custom.roles.admin'),
                    'payment_type' => '',
                ]);

                $this->command->info('Superadmin created successfully.');

                $adminRole = Role::create(['name' => 'superadmin', 'display_name' => 'SuperAdmin']);
                $role = Role::findByName('superadmin');
                $user->assignRole($role);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create superadmin: '.$e->getMessage());
        }
    }
}
