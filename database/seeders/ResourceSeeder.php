<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Traits\TransactionWrapper;
use App\Api\Admin\Modules\Resources\Models\Resources;

class ResourceSeeder extends Seeder
{
    use TransactionWrapper;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->runInTransaction(function () {
            $resources = [
                [
                    'resource_name'      => 'Trail',  
                    'resource_reference' => 'trail',
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ],
            ];
            foreach ($resources as $resource) {
                Resources::updateOrInsert(
                    ['resource_name' => $resource['resource_name']],
                    $resource
                );
            }

            $this->command->info('Resources created successfully.');
        });
    }
}
