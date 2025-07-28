<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // $loader = \Illuminate\Foundation\AliasLoader::getInstance();
        // $loader->alias('Debugbar', \Barryvdh\Debugbar\Facades\Debugbar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
        $this->loadModuleApi();
    }

    protected function loadModuleApi(): void
    {
        $adminModules = File::directories(app_path('Api/Admin/Modules'));
        $teacherModules = File::directories(app_path('Api/Teacher/Modules'));
        $parentModules = File::directories(app_path('Api/Parent/Modules'));

        $modules = array_merge($adminModules, $teacherModules, $parentModules);
        foreach ($modules as $modulePath) {
            // $module = basename($modulePath);
            $routesPath = $modulePath.'/routes.php';
            if (file_exists($routesPath)) {
                Route::prefix('api')->middleware('api')
                    ->group(function () use ($routesPath) {
                        require $routesPath;
                    });
            }
        }
    }
}
