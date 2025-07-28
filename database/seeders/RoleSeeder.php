<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds to create predefined roles.
     *
     * This method inserts or updates roles using updateOrInsert inside a database transaction.
     * If any part of the insertion fails, the transaction is rolled back to maintain consistency.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $roles = [
                [
                    'name' => 'superadmin',
                    'display_name' => 'Super Admin',
                    'guard_name' => 'web',
                    'type' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'teacher',
                    'display_name' => 'Teacher',
                    'guard_name' => 'web',
                    'type' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'parents',
                    'display_name' => 'Parents',
                    'guard_name' => 'web',
                    'type' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            foreach ($roles as $role) {
                Role::updateOrInsert(
                    ['name' => $role['name']],
                    $role
                );
            }
            DB::commit();
            $this->command->info('Roles created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create roles: '.$e->getMessage());
        }
    }
}
