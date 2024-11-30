<?php

namespace CitraGroup\Platform;

use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use CitraGroup\Platform\Console\Commands\PlatformInstall;
use Citrapp\Platform\Console\Commands\PlatformModulePull;
use Citrapp\Platform\Console\Commands\PlatformModuleUpdate;
use CitraGroup\Platform\Console\Commands\PlatformModuleList;
use CitraGroup\Platform\Console\Commands\PlatformModuleSeed;
use CitraGroup\Platform\Console\Commands\PlatformModuleClone;
use CitraGroup\Platform\Console\Commands\PlatformModuleDelete;
use CitraGroup\Platform\Console\Commands\PlatformModuleInstall;
use CitraGroup\Platform\Console\Commands\PlatformModuleMigrate;

class ModularServiceProvider extends ServiceProvider
{
    /**
     * boot function
     *
     * @return void
     */
    public function boot(): void
    {
        /** Disable wrapping of the outer-most resource array. */
        JsonResource::withoutWrapping();

        /** Prevent model relationships from being lazy loaded. */
        Model::preventLazyLoading();

        /** Prevent non-fillable attributes from being silently discarded. */
        Model::preventSilentlyDiscardingAttributes();

        /** Register Artisan Commands */
        $this->registerArtisanCommands();

        /** Boot and Register Modules */
        $this->bootAndRegisterModules();

        /** Publish asset, config and frontend-components */
        $this->publishes([
            __DIR__ . '/../.eslintrc.js' => base_path('.eslintrc.js'),
            __DIR__ . '/../config/database.php' => config_path('database.php'),
            __DIR__ . '/../config/cors.php' => config_path('cors.php'),
            __DIR__ . '/../modules' => base_path('modules'),
            __DIR__ . '/../routes' => base_path('routes'),
            __DIR__ . '/../seeders' => database_path('seeders'),
        ], 'citragroup-config');
    }

    /**
     * register function
     *
     * @return void
     */
    public function register(): void
    {
        URL::forceScheme('https');

        Fortify::ignoreRoutes();
    }

    /**
     * registerArtisanCommands function
     *
     * @return void
     */
    protected function registerArtisanCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PlatformInstall::class,
                PlatformModuleClone::class,
                PlatformModuleDelete::class,
                PlatformModuleInstall::class,
                PlatformModuleList::class,
                PlatformModuleMigrate::class,
                PlatformModulePull::class,
                PlatformModuleSeed::class,
                PlatformModuleUpdate::class
            ]);
        }
    }

    /**
     * bootAndRegisterModules function
     *
     * @return void
     */
    protected function bootAndRegisterModules(): void
    {
        $modules = Cache::has('modules') && count(Cache::get('modules')) > 0 ?
            Cache::get('modules') :
            $this->scanModulesFolder();

        foreach ($modules as $module) {
            if (!File::exists(base_path('modules' . DIRECTORY_SEPARATOR . str($module->name)->lower()))) {
                continue;
            }

            if ($module->providers && is_array($module->providers)) {
                foreach ($module->providers as $provider) {
                    if (class_exists($provider)) {
                        with(new $provider($this->app))->boot();
                        with(new $provider($this->app))->register();
                    }
                }
            } else {
                if (class_exists($module->providers)) {
                    with(new $module->providers($this->app))->boot();
                    with(new $module->providers($this->app))->register();
                }
            }
        }
    }

    /**
     * scanModulesFolder function
     *
     * @return array
     */
    protected function scanModulesFolder(): array
    {
        Cache::forget('modules');

        return Cache::rememberForever('modules', function () {
            $modules = [];

            /** Scan All-Module Except System */
            $folders = glob(base_path('modules') . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);

            foreach ($folders as $folder) {
                $json_path = $folder . DIRECTORY_SEPARATOR . 'module.json';

                if (!File::exists($json_path)) {
                    continue;
                }

                $content                = file_get_contents($json_path);
                $json_data              = json_decode($content, true);
                $module_name            = $json_data['name'];
                $json_data['directory'] = $folder;
                $modules[$module_name]  = $json_data;
            }

            if (count($modules) === 0) {
                return $modules;
            }

            /** Sort data by priority */
            array_multisort(array_column($modules, 'priority'), SORT_ASC, $modules);

            /** Convert array to object */
            foreach ($modules as $key => $module) {
                $modules[$key] = json_decode(json_encode($module), false);
            }

            return $modules;
        });
    }
}
