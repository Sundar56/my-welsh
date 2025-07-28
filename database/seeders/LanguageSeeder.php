<?php

namespace Database\Seeders;

use App\Models\Languages;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder populates the `languages` table with default language records.
     * It uses a transaction to ensure data integrity in case of failure.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $languages = [
                [
                    'languages' => 'English',
                    'code' => 'en',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'languages' => 'Welsh',
                    'code' => 'cy',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            foreach ($languages as $language) {
                Languages::updateOrInsert(
                    ['code' => $language['code']],
                    $language
                );
            }
            DB::commit();
            $this->command->info('Languages created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to create Languages: '.$e->getMessage());
        }
    }
}
// php artisan db:seed --class=LanguageSeeder
