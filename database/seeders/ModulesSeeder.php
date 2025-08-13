<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Traits\TransactionWrapper;
use App\Models\Modules;

class ModulesSeeder extends Seeder
{
    use TransactionWrapper;
    /**  
     * Run the database seeds.
     * 
     * This seeder populates the `modules` table with default modules records.
     */
    public function run(): void
    {

        $this->runInTransaction(function () {
            $modules = [
                [
                    'name'          => 'Resources',
                    'order'         => '1',
                    'slug'          => 'user/resources',
                    'type'          => 1,
                    'icon'          => 'assets/images/resources-icon.svg',
                    'frontend_slug' => 'resources',
                    'cy_name'       => 'Adnoddau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Your Playlists',
                    'order'         => '2',
                    'slug'          => 'user/playlists',
                    'type'          => 1,
                    'icon'          => 'assets/images/your-playlist-icon.svg',
                    'frontend_slug' => 'playlists-your',
                    'cy_name'       => 'Eich rhestri chwarae',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'User Settings',
                    'order'         => '3',
                    'slug'          => 'user/usersettings',
                    'type'          => 1,
                    'icon'          => 'assets/images/user-setting-icon.svg',
                    'frontend_slug' => 'settings',
                    'cy_name'       => 'Gosodiadau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Dashboard',
                    'order'         => '4',
                    'slug'          => 'dashboard',
                    'type'          => 0,
                    'icon'          => 'assets/images/dashboard-icon.svg',
                    'frontend_slug' => 'admin/dashboard',
                    'cy_name'       => 'Dangosfwrdd',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Add / Edit',
                    'order'         => '5',
                    'slug'          => 'add-edit',
                    'main_module'   => 'add-edit',
                    'sub_module'    => '',
                    'type'          => 0,
                    'is_mainmodule' => 1,
                    'icon'          => 'assets/images/add-icon.svg',
                    'frontend_slug' => 'admin/add-edit',
                    'cy_name'       => 'Ychwanegu / Golygu',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Add Resources',
                    'order'         => '6',
                    'slug'          => 'add-resources',
                    'main_module'   => 'add-edit',
                    'sub_module'    => 'add-resources',
                    'type'          => 0,
                    'is_submodule'  => 1,
                    'icon'          => '',
                    'frontend_slug' => 'admin/resources-add',
                    'cy_name'       => 'Ychwanegu Adnoddau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Edit Resources',
                    'order'         => '7',
                    'slug'          => 'edit-resources',
                    'main_module'   => 'add-edit',
                    'sub_module'    => 'edit-resources',
                    'type'          => 0,
                    'is_submodule'  => 1,
                    'icon'          => '',
                    'frontend_slug' => 'gweinyddiaeth/adnoddau',
                    'cy_name'       => 'Golygu Adnoddau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Resources',
                    'order'         => '8',
                    'slug'          => 'resources',
                    'type'          => 0,
                    'icon'          => 'assets/images/resources-icon.svg',
                    'frontend_slug' => 'admin/resources',
                    'cy_name'       => 'Adnoddau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Your Playlists',
                    'order'         => '9',
                    'slug'          => 'playlists',
                    'type'          => 0,
                    'icon'          => 'assets/images/your-playlist-icon.svg',
                    'frontend_slug' => 'admin/playlists',
                    'cy_name'       => 'Eich rhestri chwarae',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'User Settings',
                    'order'         => '10',
                    'slug'          => 'usersettings',
                    'type'          => 0,
                    'icon'          => 'assets/images/user-setting-icon.svg',
                    'frontend_slug' => 'admin/settings',
                    'cy_name'       => 'Gosodiadau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Customers',
                    'order'         => '11',
                    'slug'          => 'customers',
                    'type'          => 0,
                    'icon'          => 'assets/images/customer-icon.svg',
                    'frontend_slug' => 'admin/customers',
                    'cy_name'       => 'Cwsmeriaid',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'Your Playlists',
                    'order'         => '12',
                    'slug'          => 'playlists-parent',
                    'type'          => 3,
                    'icon'          => 'assets/images/your-playlist-icon.svg',
                    'frontend_slug' => 'playlists-parent',
                    'cy_name'       => 'Eich rhestri chwarae',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
                [
                    'name'          => 'User Settings',
                    'order'         => '13',
                    'slug'          => 'settings-parent',
                    'type'          => 3,
                    'icon'          => 'assets/images/user-setting-icon.svg',
                    'frontend_slug' => 'settings-parent',
                    'cy_name'       => 'Gosodiadau',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ],
            ];
            foreach ($modules as &$module) {
                if (!empty($module['icon'])) {
                    $path = public_path($module['icon']);
                    if (file_exists($path)) {
                        $svgContent = file_get_contents($path);
                        $base64 = base64_encode($svgContent);
                        $module['icon'] = 'data:image/svg+xml;base64,' . $base64;
                    } else {
                        $module['icon'] = '';
                    }
                }
            }
            unset($module);

            foreach ($modules as $module) {
                Modules::updateOrInsert(
                    ['slug' => $module['slug']],
                    $module
                );
            }

            $this->command->info('Modules created successfully.');
        });
    }
}
// php artisan db:seed --class=ModulesSeeder
