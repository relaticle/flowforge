<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Commands\MakeKanbanBoardCommand;
use Relaticle\Flowforge\FlowforgeServiceProvider;
use Symfony\Component\Finder\SplFileInfo;

test('service provider is registered', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)->toHaveKey(FlowforgeServiceProvider::class);
});

test('artisan command is registered', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('flowforge:make-board');
    expect($commands['flowforge:make-board'])->toBeInstanceOf(MakeKanbanBoardCommand::class);
});

test('package assets are defined', function () {
    $serviceProvider = new FlowforgeServiceProvider(app());

    // Use reflection to test protected getAssets method
    $reflection = new ReflectionClass($serviceProvider);
    $method = $reflection->getMethod('getAssets');
    $method->setAccessible(true);
    $assets = $method->invoke($serviceProvider);

    expect($assets)->toHaveCount(1); // Only JS asset exists now
    expect($assets[0]->getId())->toBe('flowforge');
});

test('blade component namespace is registered', function () {
    $namespaces = Blade::getClassComponentNamespaces();

    expect($namespaces)->toHaveKey('flowforge');
    expect($namespaces['flowforge'])->toBe('Relaticle\\Flowforge\\View\\Components');
});

test('package views are registered', function () {
    // Test that views can be resolved
    expect(view()->exists('flowforge::index'))->toBeTrue();
    expect(view()->exists('flowforge::filament.pages.board-page'))->toBeTrue();
    expect(view()->exists('flowforge::livewire.column'))->toBeTrue();
    expect(view()->exists('flowforge::livewire.card'))->toBeTrue();
});

test('views registered without the panel builder do not use panel components', function () {
    // `php artisan view:cache` compiles every view in the namespace, so one
    // panel component here breaks it for apps without filament/filament.
    $viewsUsingPanelComponents = collect(File::allFiles(__DIR__ . '/../../resources/views'))
        ->filter(fn (SplFileInfo $file) => str_contains($file->getContents(), 'filament-panels::'))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($viewsUsingPanelComponents)->toBe([]);
});

test('package translations are available', function () {
    expect(__('flowforge::flowforge.loading_more_cards'))
        ->toBe('Loading more cards...');

    expect(__('flowforge::flowforge.no_cards_in_column', ['cardLabel' => 'tasks']))
        ->toBe('No tasks in this column');
});

test('package script data is configured', function () {
    $serviceProvider = new FlowforgeServiceProvider(app());

    // Use reflection to test protected getScriptData method
    $reflection = new ReflectionClass($serviceProvider);
    $method = $reflection->getMethod('getScriptData');
    $method->setAccessible(true);
    $scriptData = $method->invoke($serviceProvider);

    expect($scriptData)->toHaveKey('flowforge');
    expect($scriptData['flowforge'])->toHaveKey('baseUrl');
    expect($scriptData['flowforge']['baseUrl'])->toBe(url('/'));
});

test('package configuration exists if defined', function () {
    // Test that config is loaded if the file exists
    if (file_exists(__DIR__ . '/../../config/flowforge.php')) {
        expect(config('flowforge'))->toBeArray();
    } else {
        // If no config file, that's valid too
        expect(true)->toBeTrue();
    }
});

test('core classes exist and are autoloadable', function () {
    expect(class_exists(Board::class))->toBeTrue();
    expect(class_exists(BoardPage::class))->toBeTrue();
    expect(class_exists(Column::class))->toBeTrue();
    expect(class_exists(MakeKanbanBoardCommand::class))->toBeTrue();
});
