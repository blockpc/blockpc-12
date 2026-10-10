<?php

declare(strict_types=1);

namespace Blockpc\App\Providers;

use Blockpc\App\Console\Commands\CreateModuleCommand;
use Blockpc\App\Console\Commands\DeleteModuleCommand;
use Blockpc\App\Console\Commands\DumpAutoloadCommand;
use Blockpc\App\Console\Commands\SyncPermissionsCommand;
use Blockpc\App\Console\Commands\SyncRolesAndPermissionsCommand;
use Blockpc\App\Console\Commands\SyncRolesCommand;
use Blockpc\App\Livewire\CustomModal;
use Blockpc\App\Livewire\Examples\CreateExample;
use Blockpc\App\Livewire\MessageAlerts;
use Blockpc\App\Livewire\Notifications\ButtonShowNotifications;
use Blockpc\App\Livewire\Notifications\SidebarNotification;
use Blockpc\App\Mixins\QuerySearchMixin;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

final class BlockpcServiceProvider extends ServiceProvider
{
    /**
     * Cache key for menu hash validation
     */
    private const MENU_HASH_CACHE_KEY = 'menus_keys_hash';

    /**
     * Cache key for ordered menus
     */
    private const ORDERED_MENUS_CACHE_KEY = 'ordered_menus';

    /**
     * Base directory for Blockpc resources
     */
    private const BLOCKPC_DIR = 'Blockpc/';

    /**
     * Packages directory name
     */
    private const PACKAGES_DIR = 'Packages';

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $menus = [];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerAuthProvider();
        $this->registerQueryMixin();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureLocalization();
        $this->configurePasswordRules();
        $this->registerConsoleCommands();
        $this->loadPackageServiceProviders();
        $this->configureBladeComponents();
        $this->loadViews();
        $this->loadLivewireComponents();
        $this->registerMenuSingleton();
    }

    /**
     * Load and register Livewire components.
     */
    protected function loadLivewireComponents(): void
    {
        // Core components
        Livewire::component('message-alerts', MessageAlerts::class);
        Livewire::component('custom-modal', CustomModal::class);
        Livewire::component('create-example', CreateExample::class);

        // Notification components
        Livewire::component('blockpc-btn-notifications', ButtonShowNotifications::class);
        Livewire::component('blockpc-sidebar-notifications', SidebarNotification::class);
    }

    /**
     * Load service providers from packages and extract menu configurations.
     */
    protected function loadPackageServiceProviders(): void
    {
        $files = $this->app->make(Filesystem::class);
        $this->menus = [];

        $packagesPath = base_path(self::PACKAGES_DIR);

        if (! $files->exists($packagesPath)) {
            return;
        }

        foreach ($files->directories($packagesPath) as $directory) {
            $this->processPackageDirectory($files, $directory);
        }
    }

    /**
     * Register authentication service provider.
     */
    private function registerAuthProvider(): void
    {
        $this->app->register(BlockpcAuthServiceProvider::class);
    }

    /**
     * Register query search mixin for Eloquent Builder.
     */
    private function registerQueryMixin(): void
    {
        Builder::mixin(new QuerySearchMixin);
    }

    /**
     * Configure model settings.
     */
    private function configureModels(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }

    /**
     * Configure localization settings.
     */
    private function configureLocalization(): void
    {
        Carbon::setLocale(config('app.locale'));
    }

    /**
     * Configure password validation rules.
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                        ? $rule->mixedCase()->uncompromised()
                        : $rule;
        });
    }

    /**
     * Register console commands when running in console.
     */
    private function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncPermissionsCommand::class,
                SyncRolesAndPermissionsCommand::class,
                SyncRolesCommand::class,
                DumpAutoloadCommand::class,
                CreateModuleCommand::class,
                DeleteModuleCommand::class,
            ]);
        }
    }

    /**
     * Configure Blade anonymous components.
     */
    private function configureBladeComponents(): void
    {
        $blockpcDir = base_path(self::BLOCKPC_DIR);
        Blade::anonymousComponentPath($blockpcDir.'resources/views', 'blockpc');
    }

    /**
     * Load views from Blockpc directory.
     */
    private function loadViews(): void
    {
        $blockpcDir = base_path(self::BLOCKPC_DIR);
        $this->loadViewsFrom($blockpcDir.'resources/views', 'blockpc');
    }

    /**
     * Register the menu singleton service.
     */
    private function registerMenuSingleton(): void
    {
        $this->app->singleton('menus', function () {
            $this->validateMenuCache();

            return Cache::rememberForever(self::ORDERED_MENUS_CACHE_KEY, function () {
                return $this->getOrderedMenus();
            });
        });
    }

    /**
     * Process a single package directory.
     */
    private function processPackageDirectory(Filesystem $files, string $directory): void
    {
        $directoryName = Str::afterLast($directory, DIRECTORY_SEPARATOR);

        $this->registerPackageServiceProvider($directoryName);
        $this->loadPackageMenuConfiguration($files, $directory);
    }

    /**
     * Register package service provider if it exists.
     */
    private function registerPackageServiceProvider(string $directoryName): void
    {
        $serviceProviderClass = "Packages\\{$directoryName}\\App\\Providers\\{$directoryName}ServiceProvider";

        if (class_exists($serviceProviderClass)) {
            $this->app->register($serviceProviderClass);
        }
    }

    /**
     * Load menu configuration from package.
     */
    private function loadPackageMenuConfiguration(Filesystem $files, string $directory): void
    {
        $configPath = "{$directory}/config/config.php";

        if (! $files->exists($configPath)) {
            return;
        }

        $config = require $configPath;
        $menu = $this->extractValidMenuFromConfig($config);

        if ($menu !== null) {
            $this->menus[] = $menu;
        }
    }

    /**
     * Extract valid menu from configuration array.
     *
     * @param  mixed  $config
     * @return array<string, mixed>|null
     */
    private function extractValidMenuFromConfig($config): ?array
    {
        if (! is_array($config) || ! isset($config['menus']) || ! is_array($config['menus'])) {
            return null;
        }

        if (empty($config['menus'])) {
            return null;
        }

        $firstMenu = reset($config['menus']);

        if (! is_array($firstMenu)) {
            return null;
        }

        if (! array_key_exists('id', $firstMenu) || $firstMenu['id'] === null) {
            return null;
        }

        return $firstMenu;
    }

    /**
     * Validate menu cache by checking if menu IDs have changed.
     */
    private function validateMenuCache(): void
    {
        $menuIds = array_map(fn ($menu) => $menu['id'] ?? 0, $this->menus);
        $currentHash = md5(implode(',', $menuIds));
        $cachedHash = Cache::get(self::MENU_HASH_CACHE_KEY);

        if ($cachedHash !== $currentHash) {
            Cache::forget(self::ORDERED_MENUS_CACHE_KEY);
            Cache::put(self::MENU_HASH_CACHE_KEY, $currentHash);
        }
    }

    /**
     * Get menus ordered by their ID.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getOrderedMenus(): array
    {
        $menus = $this->menus;

        uasort($menus, function ($a, $b) {
            $aId = $a['id'] ?? PHP_INT_MAX;
            $bId = $b['id'] ?? PHP_INT_MAX;

            return $aId <=> $bId;
        });

        return $menus;
    }
}
