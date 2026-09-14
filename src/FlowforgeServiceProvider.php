<?php

namespace Relaticle\Flowforge;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Relaticle\Flowforge\Commands\DiagnosePositionsCommand;
use Relaticle\Flowforge\Commands\MakeKanbanBoardCommand;
use Relaticle\Flowforge\Commands\RebalancePositionsCommand;
use Relaticle\Flowforge\Commands\RepairPositionsCommand;
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
        Blade::component('flowforge::livewire.swimlane-board', 'flowforge::swimlane-board');
        Blade::component('flowforge::livewire.swimlane-cell', 'flowforge::swimlane-cell');
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
