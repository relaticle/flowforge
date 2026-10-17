<?php

namespace Relaticle\Flowforge;

use Filament\Panel;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Relaticle\Flowforge\Commands\DiagnosePositionsCommand;
use Relaticle\Flowforge\Commands\MakeKanbanBoardCommand;
use Relaticle\Flowforge\Commands\RebalancePositionsCommand;
use Relaticle\Flowforge\Commands\RepairPositionsCommand;
use Relaticle\Flowforge\Modern\Concerns\InteractsWithBoard as ModernInteractsWithBoard;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FlowforgeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'flowforge';

    public static string $viewNamespace = 'flowforge';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('relaticle/flowforge');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void {}

    public function packageBooted(): void
    {
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        $this->registerModernAssets();

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Register Blade Components
        $this->registerBladeComponents();

        // Register Blueprint Macros
        $this->registerBlueprintMacros();

        // Register Panel Views
        $this->registerPanelViews();
    }

    /**
     * Register the views that render Filament Panels components.
     *
     * They live outside the default views directory and are only registered when
     * filament/filament is installed, so `php artisan view:cache` keeps working
     * for standalone usage of the InteractsWithBoard trait.
     */
    private function registerPanelViews(): void
    {
        if (! class_exists(Panel::class)) {
            return;
        }

        $panelViewsPath = __DIR__ . '/../resources/panel-views';

        $this->loadViewsFrom($panelViewsPath, static::$viewNamespace);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $panelViewsPath => resource_path('views/vendor/' . static::$viewNamespace),
            ], static::$viewNamespace . '-views');
        }
    }

    /**
     * Register the package Blade components.
     */
    private function registerBladeComponents(): void
    {
        // Register kanban board related components
        Blade::componentNamespace('Relaticle\\Flowforge\\View\\Components', 'flowforge');

        // Manually register components that don't have a class
        Blade::component('flowforge::livewire.board', 'flowforge::board');
        Blade::component('flowforge::livewire.column', 'flowforge::column');
        Blade::component('flowforge::livewire.empty-column', 'flowforge::empty-column');
        Blade::component('flowforge::livewire.card', 'flowforge::card');
        Blade::component('flowforge::livewire.column-count', 'flowforge::column-count');

        Blade::component('flowforge::modern.livewire.column', 'flowforge::modern.column');
        Blade::component('flowforge::modern.livewire.column-header', 'flowforge::modern.column-header');
        Blade::component('flowforge::modern.livewire.card', 'flowforge::modern.card');
        Blade::component('flowforge::modern.livewire.column-count', 'flowforge::modern.column-count');
        Blade::component('flowforge::modern.livewire.add-card-row', 'flowforge::modern.add-card-row');
        Blade::component('flowforge::modern.livewire.lanes', 'flowforge::modern.lanes');
        Blade::component('flowforge::modern.components.grouping-picker', 'flowforge::modern.grouping-picker');
    }

    protected function getAssetPackageName(): ?string
    {
        return 'relaticle/flowforge';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            AlpineComponent::make('flowforge', __DIR__ . '/../resources/dist/flowforge.js'),
        ];
    }

    private function registerModernAssets(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('flowforge-modern', __DIR__ . '/../resources/dist/flowforge-modern.js'),
            Css::make('flowforge-modern', __DIR__ . '/../resources/css/flowforge-modern.css')->loadedOnRequest(),
        ], $this->getAssetPackageName());

        if (class_exists(PanelsRenderHook::class)) {
            FilamentView::registerRenderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (array $scopes): string => $this->rendersModernBoard($scopes)
                    ? view('flowforge::modern.stylesheet')->render()
                    : '',
            );
        }
    }

    /**
     * @param  array<int, mixed>  $scopes
     */
    private function rendersModernBoard(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (is_string($scope) && class_exists($scope) && in_array(ModernInteractsWithBoard::class, class_uses_recursive($scope), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            DiagnosePositionsCommand::class,
            MakeKanbanBoardCommand::class,
            RebalancePositionsCommand::class,
            RepairPositionsCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [
            'flowforge' => [
                'baseUrl' => url('/'),
            ],
        ];
    }

    /**
     * Register Blueprint macros for Flowforge.
     */
    private function registerBlueprintMacros(): void
    {
        // DECIMAL(20,10) for position - 10 integer digits + 10 decimal places
        // Supports ~33 bisections before precision loss, with 65535 gap
        Blueprint::macro('flowforgePositionColumn', function (string $name = 'position') {
            return $this->decimal($name, 20, 10)->nullable();
        });
    }
}
