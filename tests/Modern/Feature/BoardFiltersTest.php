<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestContractBoard;

describe('board filters', function () {
    test('renders the reset filters action', function (string $layout) {
        Livewire::test(TestContractBoard::class, ['filtersLayout' => $layout])
            ->assertStatus(200)
            ->assertSeeHtml('wire:click="resetTableFiltersForm"');
    })->with(['Dropdown', 'Modal', 'AboveContent', 'AboveContentCollapsible', 'BelowContent', 'BeforeContent', 'AfterContent']);

    test('renders the reset filters action in the header toolbar', function () {
        Livewire::test(TestContractBoard::class, ['withHeaderToolbar' => true, 'filtersLayout' => 'Dropdown'])
            ->assertStatus(200)
            ->assertSeeHtml('wire:click="resetTableFiltersForm"');
    });

    test('applies the board reset filters action modifier', function () {
        Livewire::test(TestContractBoard::class, ['filtersLayout' => 'Dropdown', 'resetFiltersLabel' => 'Clear every filter'])
            ->assertSee('Clear every filter');
    });
});
