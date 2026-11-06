<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoardResourcePage;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestHiddenActionsBoard;

beforeEach(function () {
    Task::create(['title' => 'Shell card', 'status' => 'todo', 'order_position' => '65535.0000000000']);
});

test('a card renders on the ring shell instead of a border', function () {
    $html = Livewire::test(TestBoardResourcePage::class)->html();

    expect($html)->toContain('ff-card')
        ->and($html)->not->toContain('border border-gray-200');
});

test('record actions sit in a corner that css reveals without a stacking layer', function () {
    $html = Livewire::test(TestBoardResourcePage::class)->html();

    expect($html)->toContain('ff-card-actions')
        ->and($html)->not->toMatch('/class="[^"]*\bff-card-actions\b[^"]*\bz-10\b/')
        ->and($html)->not->toMatch('/class="[^"]*\bz-10\b[^"]*\bff-card-actions\b/');
});

test('each card carries a wire key scoped to the flat board', function () {
    $task = Task::first();

    Livewire::test(TestBoardResourcePage::class)
        ->assertSeeHtml('wire:key="card-board-' . $task->id . '"');
});

test('a card whose actions are all hidden renders no action menu', function () {
    Livewire::test(TestHiddenActionsBoard::class)
        ->assertSeeHtml('ff-card-body')
        ->assertDontSeeHtml('ff-card-actions');
});

test('a column whose actions are all hidden renders no action menu', function () {
    Livewire::test(TestHiddenActionsBoard::class)
        ->assertSeeHtml('ff-column-title')
        ->assertDontSeeHtml('ff-column-actions');
});
