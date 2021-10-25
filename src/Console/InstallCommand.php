<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larastarters:install
                            {--theme=windmill : Theme name with which breeze should be replaced}
                            {--composer=global : Absolute path to the Composer binary which should be used to install packages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install one of the Larastarters Themes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Install breeze
        $this->requireComposerPackages('laravel/breeze:^1.4');
        shell_exec('php artisan breeze:install');

        if ($this->option('theme') === 'windmill') {
            return $this->replaceWindmill();
        }

        if ($this->option('theme') === 'notusjs') {
            return $this->replaceWithNotusjs();
        }

        if ($this->option('theme') === 'tailwindcomponents') {
            return $this->replaceWithTailwindComponents();
        }
    }

    protected function replaceWindmill()
    {
        // NPM Packages...
        $this->updateNodePackages(function ($packages) {
            return [
                'color' => '^4.0.1',
                'tailwindcss-multi-theme' => '^1.0.4'
            ] + $packages;
        });

        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/auth'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/layouts'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/components'));
        (new Filesystem)->ensureDirectoryExists(public_path('images'));
        (new Filesystem)->ensureDirectoryExists(public_path('js'));

        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/windmill/views/auth', resource_path('views/auth'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/windmill/views/layouts', resource_path('views/layouts'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/windmill/views/components', resource_path('views/components'));

        copy(__DIR__.'/../../resources/stubs/windmill/views/dashboard.blade.php', resource_path('views/dashboard.blade.php'));

        // Assets
        copy(__DIR__ . '/../../resources/stubs/windmill/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__ . '/../../resources/stubs/windmill/css/app.css', resource_path('css/app.css'));
        copy(__DIR__ . '/../../resources/stubs/windmill/js/app.js', resource_path('js/app.js'));
        copy(__DIR__ . '/../../resources/stubs/windmill/js/init-alpine.js', public_path('js/init-alpine.js'));

        // Images
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/windmill/images', public_path('images'));

        $this->info('Breeze scaffolding replaced successfully.');
        $this->comment('Please execute the "npm install && npm run dev" command to build your assets.');
    }

    protected function replaceWithNotusjs()
    {
        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/auth'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/layouts'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/components'));
        (new Filesystem)->ensureDirectoryExists(public_path('images'));

        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/notusjs/views/auth', resource_path('views/auth'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/notusjs/views/layouts', resource_path('views/layouts'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/notusjs/views/components', resource_path('views/components'));

        copy(__DIR__.'/../../resources/stubs/notusjs/views/dashboard.blade.php', resource_path('views/dashboard.blade.php'));

        // Assets
        copy(__DIR__ . '/../../resources/stubs/notusjs/tailwind.config.js', base_path('tailwind.config.js'));

        // Images
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/notusjs/images', public_path('images'));

        $this->info('Breeze scaffolding replaced successfully.');
        $this->comment('Please execute the "npm install && npm run dev" command to build your assets.');
    }

    protected function replaceWithTailwindComponents()
    {
        // Views...
        (new Filesystem)->ensureDirectoryExists(resource_path('views/auth'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/layouts'));
        (new Filesystem)->ensureDirectoryExists(resource_path('views/components'));

        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/tailwindcomponents/views/auth', resource_path('views/auth'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/tailwindcomponents/views/layouts', resource_path('views/layouts'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../resources/stubs/tailwindcomponents/views/components', resource_path('views/components'));

        copy(__DIR__.'/../../resources/stubs/tailwindcomponents/views/dashboard.blade.php', resource_path('views/dashboard.blade.php'));

        $this->info('Breeze scaffolding replaced successfully.');
        $this->comment('Please execute the "npm install && npm run dev" command to build your assets.');
    }

    /**
     * Installs the given Composer Packages into the application.
     * Taken from https://github.com/laravel/breeze/blob/1.x/src/Console/InstallCommand.php
     *
     * @param mixed $packages
     * @return void
     */
    protected function requireComposerPackages($packages)
    {
        $composer = $this->option('composer');

        if ($composer !== 'global') {
            $command = ['php', $composer, 'require'];
        }

        $command = array_merge(
            $command ?? ['composer', 'require'],
            is_array($packages) ? $packages : func_get_args()
        );

        (new Process($command, base_path(), ['COMPOSER_MEMORY_LIMIT' => '-1']))
            ->setTimeout(null)
            ->run(function ($type, $output) {
                $this->output->write($output);
            });
    }

    /**
     * Update the "package.json" file.
     * Taken from https://github.com/laravel/breeze/blob/1.x/src/Console/InstallCommand.php
     *
     * @param callable $callback
     * @param bool $dev
     * @return void
     */
    protected static function updateNodePackages(callable $callback, $dev = true)
    {
        if (!file_exists(base_path('package.json'))) {
            return;
        }

        $configurationKey = $dev ? 'devDependencies' : 'dependencies';

        $packages = json_decode(file_get_contents(base_path('package.json')), true);

        $packages[$configurationKey] = $callback(
            array_key_exists($configurationKey, $packages) ? $packages[$configurationKey] : [],
            $configurationKey
        );

        ksort($packages[$configurationKey]);

        file_put_contents(
            base_path('package.json'),
            json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }
}