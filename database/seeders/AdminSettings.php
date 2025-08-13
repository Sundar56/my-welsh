<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminSettings extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $roleId = DB::table('roles')->where('name', 'superadmin')->value('id');
            $userId = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->value('model_id');

            DB::table('settings')->insert([
                'user_id'     => $userId,
                'apikey'      => env('STRIPE_KEY'),
                'apisecret'   => env('STRIPE_SECRET'),
                'webhookkey'  => env('STRIPE_WEBHOOK_ENDPOINT_KEY'),
                'webhookurl'  => env('STRIPE_WEBHOOK'),
                'fixedfee'   => env('FIXEDFEE'),
                'percentagefee'  => env('PERCENTAGE_FEE'),
                'title'  => env('TITLE'),
                'description'  => env('DESCRIPTION'),
                'keyword'  => env('KEYWORD'),
                'logo'  => env('FFALALA_LOGO'),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::commit();
            echo "Admin Settings seeder ran successfully.\n";
        } catch (\Exception $e) {
            DB::rollback();
            echo "Failed to seed Admin Settings table.\n";
        }
    }
}
