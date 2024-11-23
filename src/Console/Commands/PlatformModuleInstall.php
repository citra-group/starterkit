<?php

namespace CitraGroup\Platform\Console\Commands;

use Illuminate\Console\Command;

class PlatformModuleInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'module:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make monosoft module install';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gitbase    = env('GITBASE');
        $modules    = explode(",", env('MODULES', ''));

        foreach ($modules as $module) {
            $this->call('module:clone', [
                'repository' => $gitbase . DIRECTORY_SEPARATOR . str($module)->before("|")->toString(),
                '--directory' => str($module)->after("|")->toString()
            ]);
        }
    }
}
